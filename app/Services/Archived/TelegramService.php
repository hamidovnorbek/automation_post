<?php

namespace App\Services;

use App\Models\Post;
use App\Services\Traits\ResolvesMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    use ResolvesMedia;

    protected function apiUrl(string $method): string
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            throw new \RuntimeException('Telegram bot token is not configured.');
        }
        return "https://api.telegram.org/bot{$token}/{$method}";
    }

    public function publish(Post $post): array
    {
        $chatId = config('services.telegram.chat_id');
        if (!$chatId) {
            throw new \RuntimeException('Telegram chat_id is not configured.');
        }
        $caption = $this->buildCaption($post);

        $photos = $this->normalizeArray($post->photos);
        $videos = $this->normalizeArray($post->videos);

        // If both present, send a media group (max 10 items) mixing types
        $mediaItems = [];
        foreach ($photos as $i => $photo) {
            $path = $this->resolvePhotoPath($photo);
            if (!$this->isValidImage($path, 10_000_000)) {
                throw new \RuntimeException('Invalid photo for Telegram: ' . $path);
            }
            $mediaItems[] = ['type' => 'photo', 'path' => $path, 'filename' => basename($path)];
        }
        foreach ($videos as $i => $video) {
            $path = $this->resolveVideoPath($video);
if (!$this->isValidVideo($path, ['mp4','mov','webm','avi'], 50 * 1024 * 1024)) { // 50MB limit
                throw new \RuntimeException('Invalid/too large video for Telegram: ' . $path);
            }
            $mediaItems[] = ['type' => 'video', 'path' => $path, 'filename' => basename($path)];
        }

        if (count($mediaItems) === 0) {
            // Send a plain text message if no media
            $resp = Http::retry(3, 1000)->asForm()->post($this->apiUrl('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $caption ?: '(no content)',
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
            $this->assertOk($resp, 'Failed to send Telegram message');
            return ['message' => $resp->json()];
        }

        // If single media, use sendPhoto/sendVideo to allow caption
        if (count($mediaItems) === 1) {
            $item = $mediaItems[0];
            if ($item['type'] === 'photo') {
                $resp = Http::retry(3, 1000)
                    ->attach('photo', file_get_contents($item['path']), $item['filename'])
                    ->post($this->apiUrl('sendPhoto'), [
                        'chat_id' => $chatId,
                        'caption' => $caption,
                        'parse_mode' => 'HTML',
                    ]);
                $this->assertOk($resp, 'Failed to send Telegram photo');
                return ['photo' => $resp->json()];
            }
            // video
            $resp = Http::retry(3, 1000)
                ->attach('video', file_get_contents($item['path']), $item['filename'])
                ->post($this->apiUrl('sendVideo'), [
                    'chat_id' => $chatId,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                    'supports_streaming' => true,
                ]);
            $this->assertOk($resp, 'Failed to send Telegram video');
            return ['video' => $resp->json()];
        }

        // Multiple media -> sendMediaGroup in batches of 10
        $allResponses = [];
        $chunks = array_chunk($mediaItems, 10);
        foreach ($chunks as $chunkIndex => $chunk) {
            $mediaJson = [];
            $attachments = [];
            foreach ($chunk as $idx => $item) {
                $attachName = $item['type'] . $idx;
                $entry = [
                    'type' => $item['type'],
                    'media' => 'attach://' . $attachName,
                ];
                if ($idx === 0 && $chunkIndex === 0) {
                    $entry['caption'] = $caption;
                    $entry['parse_mode'] = 'HTML';
                }
                $mediaJson[] = $entry;
                $attachments[] = ['name' => $attachName, 'path' => $item['path'], 'filename' => $item['filename']];
            }

            $req = Http::retry(3, 1000);
            foreach ($attachments as $att) {
                $req = $req->attach($att['name'], file_get_contents($att['path']), $att['filename']);
            }
            $resp = $req->asMultipart()->post($this->apiUrl('sendMediaGroup'), [
                [
                    'name' => 'chat_id',
                    'contents' => (string) $chatId,
                ],
                [
                    'name' => 'media',
                    'contents' => json_encode($mediaJson),
                ],
            ]);
            $this->assertOk($resp, 'Failed to send Telegram media group');
            $allResponses[] = $resp->json();
        }

        return ['media_groups' => $allResponses];
    }

    protected function buildCaption(Post $post): string
    {
        $title = trim((string) ($post->title ?? ''));
        $body = $post->body;
        $bodyText = is_array($body) ? trim(collect($body)->flatten()->implode("\n\n")) : trim((string) $body);
        $text = trim($title . (strlen($title) && strlen($bodyText) ? "\n\n" : '') . strip_tags($bodyText));
        // Telegram caption/message limits ~4096 chars, but caption limit is 1024 for media
        if (strlen($text) > 1024) {
            $text = substr($text, 0, 1015) . '…';
        }
        return $text;
    }

    protected function normalizeArray($value): array
    {
        if (is_array($value)) return array_values(array_filter($value));
        if (is_string($value) && strlen($value)) return [trim($value)];
        return [];
    }

    protected function assertOk($response, string $message): void
    {
        if (!$response->successful()) {
            throw new \RuntimeException($message . ': ' . $response->body());
        }
    }

    /**
     * Test Telegram Bot API connection
     */
    public function testPostPhoto(): array
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.channel_id') ?? config('services.telegram.chat_id');

            if (!$botToken) {
                return [
                    'success' => false,
                    'message' => 'Telegram bot token not configured',
                    'configured' => false
                ];
            }

            if (!$chatId) {
                return [
                    'success' => false,
                    'message' => 'Telegram chat ID not configured',
                    'configured' => false
                ];
            }

            // Test bot API access by getting bot info
            $response = Http::get($this->apiUrl('getMe'));

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Telegram API error: ' . $response->body(),
                    'configured' => true,
                    'api_response' => $response->json()
                ];
            }

            $botInfo = $response->json();

            // Test chat access by getting chat info
            $chatResponse = Http::post($this->apiUrl('getChat'), [
                'chat_id' => $chatId
            ]);

            $chatInfo = null;
            if ($chatResponse->successful()) {
                $chatInfo = $chatResponse->json()['result'] ?? null;
            }

            return [
                'success' => true,
                'message' => 'Telegram API connection successful',
                'configured' => true,
                'bot_info' => [
                    'id' => $botInfo['result']['id'] ?? null,
                    'username' => $botInfo['result']['username'] ?? null,
                    'first_name' => $botInfo['result']['first_name'] ?? null,
                ],
                'chat_info' => $chatInfo ? [
                    'id' => $chatInfo['id'] ?? null,
                    'title' => $chatInfo['title'] ?? null,
                    'type' => $chatInfo['type'] ?? null,
                ] : null,
                'chat_accessible' => $chatResponse->successful()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Telegram test failed: ' . $e->getMessage(),
                'configured' => true,
                'error' => $e->getMessage()
            ];
        }
    }
}
