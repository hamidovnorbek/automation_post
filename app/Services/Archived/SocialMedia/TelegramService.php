<?php

namespace App\Services\SocialMedia;

use App\Models\Post;
use Illuminate\Support\Facades\Http;

class TelegramService extends AbstractSocialMediaService
{
    protected function getPlatformName(): string
    {
        return 'telegram';
    }

    protected function getRequiredConfigFields(): array
    {
        return ['bot_token', 'chat_id'];
    }

    protected function publishPost(Post $post): array
    {
        $this->validateConfig();
        
        $botToken = $this->config['bot_token'];
        $chatId = $this->config['chat_id'];
        $mediaUrls = $this->getMediaUrls($post);
        $caption = $this->getPostText($post);
        
        try {
            if (empty($mediaUrls)) {
                return $this->sendTextMessage($caption, $botToken, $chatId);
            } elseif (count($mediaUrls) === 1) {
                return $this->sendSingleMedia($mediaUrls[0], $caption, $botToken, $chatId);
            } else {
                return $this->sendMediaGroup($mediaUrls, $caption, $botToken, $chatId);
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendTextMessage(string $text, string $botToken, string $chatId): array
    {
        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to send text message: ' . $response->body());
        }
        
        $result = $response->json();
        $messageId = $result['result']['message_id'];
        
        return [
            'success' => true,
            'external_id' => $messageId,
            'platform_url' => $this->getTelegramMessageUrl($chatId, $messageId),
            'response_data' => $result
        ];
    }

    protected function sendSingleMedia(array $media, string $caption, string $botToken, string $chatId): array
    {
        $method = $media['type'] === 'photo' ? 'sendPhoto' : 'sendVideo';
        $mediaParam = $media['type'] === 'photo' ? 'photo' : 'video';
        
        $response = Http::post("https://api.telegram.org/bot{$botToken}/{$method}", [
            'chat_id' => $chatId,
            $mediaParam => $media['url'],
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ]);
        
        if (!$response->successful()) {
            throw new \Exception("Failed to send {$media['type']}: " . $response->body());
        }
        
        $result = $response->json();
        $messageId = $result['result']['message_id'];
        
        return [
            'success' => true,
            'external_id' => $messageId,
            'platform_url' => $this->getTelegramMessageUrl($chatId, $messageId),
            'response_data' => $result
        ];
    }

    protected function sendMediaGroup(array $mediaUrls, string $caption, string $botToken, string $chatId): array
    {
        $mediaGroup = [];
        
        foreach ($mediaUrls as $index => $media) {
            $mediaItem = [
                'type' => $media['type'],
                'media' => $media['url'],
            ];
            
            // Add caption only to the first item
            if ($index === 0 && $caption) {
                $mediaItem['caption'] = $caption;
                $mediaItem['parse_mode'] = 'HTML';
            }
            
            $mediaGroup[] = $mediaItem;
        }
        
        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMediaGroup", [
            'chat_id' => $chatId,
            'media' => json_encode($mediaGroup),
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to send media group: ' . $response->body());
        }
        
        $result = $response->json();
        $firstMessageId = $result['result'][0]['message_id'];
        
        return [
            'success' => true,
            'external_id' => $firstMessageId,
            'platform_url' => $this->getTelegramMessageUrl($chatId, $firstMessageId),
            'response_data' => $result
        ];
    }

    protected function getTelegramMessageUrl(string $chatId, int $messageId): string
    {
        // For public channels, you can create direct links
        // For private chats, this will just be a placeholder
        if (str_starts_with($chatId, '@')) {
            $username = substr($chatId, 1);
            return "https://t.me/{$username}/{$messageId}";
        }
        
        return "https://t.me/c/{$chatId}/{$messageId}";
    }

    protected function uploadMedia(string $mediaPath, string $type): array
    {
        // Telegram accepts direct URLs, so no upload needed
        return [];
    }
}