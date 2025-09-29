<?php

namespace App\Services;

use App\Models\Post;
use App\Services\Traits\ResolvesMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    use ResolvesMedia;

    protected string $graphUrl = 'https://graph.facebook.com/v23.0';

    public function publish(Post $post): array
    {
        $token = config('services.instagram.access_token');
        $userId = config('services.instagram.business_account_id');

        if (!$token || !$userId) {
            throw new \RuntimeException('Instagram credentials are not configured.');
        }

        $photos = $this->normalizeArray($post->photos);
        $videos = $this->normalizeArray($post->videos);
        $caption = $this->buildCaption($post);

        // Decide what to post
        if (!empty($videos)) {
            if (count($photos) + count($videos) > 1) {
                // Create a carousel mixing images/videos
                return $this->createCarouselAndPublish($userId, $token, $photos, $videos, $caption);
            }
            // Single video
            return $this->createVideoAndPublish($userId, $token, $videos[0], $caption);
        }

        if (!empty($photos)) {
            if (count($photos) > 1) {
                return $this->createCarouselAndPublish($userId, $token, $photos, [], $caption);
            }
            // Single image
            return $this->createImageAndPublish($userId, $token, $photos[0], $caption);
        }

        // Fallback: post caption as an image with generated placeholder is not supported; IG requires media.
        throw new \RuntimeException('Instagram requires at least one media (photo or video) to publish.');
    }

    protected function createImageAndPublish(string $userId, string $token, string $photo, string $caption): array
    {
        $path = $this->resolvePhotoPath($photo);
        if (!file_exists($path) || !$this->isValidImage($path, 8_000_000)) {
            throw new \RuntimeException('Invalid image for Instagram: ' . $path);
        }
        // Auto-fix aspect ratio if needed
        $path = $this->ensureInstagramAspectCompliantImage($path, 0.8, 1.91, 8_000_000);
        $imageUrl = $this->ensureTempPublicUrl($path, 'tmp/instagram/images');

        $container = $this->retryHttp()->asForm()->post("{$this->graphUrl}/{$userId}/media", [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $token,
        ]);
        $this->assertOk($container, 'Failed to create Instagram image container');
        $creationId = $container->json('id');

        $publish = $this->retryHttp()->asForm()->post("{$this->graphUrl}/{$userId}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);
        $this->assertOk($publish, 'Failed to publish Instagram image');

        return [
            'container' => $container->json(),
            'publish' => $publish->json(),
        ];
    }

    protected function createVideoAndPublish(string $userId, string $token, string $video, string $caption): array
    {
        $path = $this->resolveVideoPath($video);
        // Instagram: Max 60s videos (for feed). Best-effort validate
        if (!$this->isValidVideo($path, ['mp4','mov','webm'])) {
            throw new \RuntimeException('Invalid video format for Instagram: ' . $path);
        }
        $duration = $this->probeVideoDurationSeconds($path);
        if ($duration !== null && $duration > 60.0) {
            throw new \RuntimeException('Instagram video exceeds 60 seconds: ' . $duration . 's');
        }
        if (!$this->isInstagramAspectRatioOk($path, 'video')) {
            Log::warning('Instagram video aspect ratio outside recommended range; attempting anyway', ['path' => $path]);
        }

        $videoUrl = $this->ensureTempPublicUrl($path, 'tmp/instagram/videos');

        $container = $this->retryHttp()->asForm()->post("{$this->graphUrl}/{$userId}/media", [
            'video_url' => $videoUrl,
            'caption' => $caption,
            'access_token' => $token,
        ]);
        $this->assertOk($container, 'Failed to create Instagram video container');

        // For videos, IG may need to wait until status is finished. Poll container status
        $creationId = $container->json('id');
        $this->waitUntilContainerIsFinished($creationId, $token);

        $publish = $this->retryHttp()->asForm()->post("{$this->graphUrl}/{$userId}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);
        $this->assertOk($publish, 'Failed to publish Instagram video');

        return [
            'container' => $container->json(),
            'publish' => $publish->json(),
        ];
    }

    protected function createCarouselAndPublish(string $userId, string $token, array $photos, array $videos, string $caption): array
    {
        $children = [];

        foreach ($photos as $photo) {
            $path = $this->resolvePhotoPath($photo);
            if (!file_exists($path) || !$this->isValidImage($path, 8_000_000)) {
                throw new \RuntimeException('Invalid image for Instagram carousel: ' . $path);
            }
            // Auto-fix aspect ratio if needed
            $path = $this->ensureInstagramAspectCompliantImage($path, 0.8, 1.91, 8_000_000);
            $url = $this->ensureTempPublicUrl($path, 'tmp/instagram/carousel');
            $resp = $this->retryHttp()->asForm()->post("{$this->graphUrl}/{$userId}/media", [
                'image_url' => $url,
                'is_carousel_item' => true,
                'access_token' => $token,
            ]);
            $this->assertOk($resp, 'Failed to create Instagram carousel image child');
            $children[] = $resp->json('id');
        }

        foreach ($videos as $video) {
            $path = $this->resolveVideoPath($video);
            if (!$this->isValidVideo($path, ['mp4','mov','webm'])) {
                throw new \RuntimeException('Invalid video for Instagram carousel: ' . $path);
            }
            $dur = $this->probeVideoDurationSeconds($path);
            if ($dur !== null && $dur > 60.0) {
                throw new \RuntimeException('Instagram carousel child video exceeds 60 seconds: ' . $dur . 's');
            }
            if (!$this->isInstagramAspectRatioOk($path, 'video')) {
                Log::warning('Instagram carousel video aspect ratio outside recommended range; attempting anyway', ['path' => $path]);
            }
            $url = $this->ensureTempPublicUrl($path, 'tmp/instagram/carousel');
            $resp = $this->retryHttp()->asForm()->post("{$this->graphUrl}/{$userId}/media", [
                'video_url' => $url,
                'is_carousel_item' => true,
                'access_token' => $token,
            ]);
            $this->assertOk($resp, 'Failed to create Instagram carousel video child');
            $children[] = $resp->json('id');
        }

        // Create parent carousel container
        $parent = $this->retryHttp()->asForm()->post("{$this->graphUrl}/{$userId}/media", [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $children),
            'caption' => $caption,
            'access_token' => $token,
        ]);
        $this->assertOk($parent, 'Failed to create Instagram carousel container');
        $creationId = $parent->json('id');

        $publish = $this->retryHttp()->asForm()->post("{$this->graphUrl}/{$userId}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);
        $this->assertOk($publish, 'Failed to publish Instagram carousel');

        return [
            'children' => $children,
            'parent' => $parent->json(),
            'publish' => $publish->json(),
        ];
    }

    protected function waitUntilContainerIsFinished(string $creationId, string $token, int $maxSeconds = 60): void
    {
        $start = time();
        while (time() - $start < $maxSeconds) {
            $status = $this->retryHttp()->get("{$this->graphUrl}/{$creationId}", [
                'fields' => 'status_code,status,video_status',
                'access_token' => $token,
            ]);
            if ($status->successful()) {
                $code = $status->json('status_code') ?? $status->json('video_status') ?? $status->json('status');
                if (in_array($code, ['FINISHED','READY','PUBLISHED'], true)) {
                    return;
                }
                if (in_array($code, ['ERROR','FAILED'], true)) {
                    throw new \RuntimeException('Instagram video container failed: ' . json_encode($status->json()))
                    ;
                }
            }
            usleep(500_000); // 0.5s
        }
        Log::warning('Timed out waiting for Instagram container to finish', ['creation_id' => $creationId]);
    }

    protected function buildCaption(Post $post): string
    {
        $title = trim((string) ($post->title ?? ''));
        $body = $post->body;
        $bodyText = is_array($body) ? trim(collect($body)->flatten()->implode("\n\n")) : trim((string) $body);
        $caption = trim($title . (strlen($title) && strlen($bodyText) ? "\n\n" : '') . strip_tags($bodyText));
        // Instagram caption max ~2200 chars
        if (strlen($caption) > 2200) {
            $caption = substr($caption, 0, 2190) . '…';
        }
        return $caption;
    }

    protected function normalizeArray($value): array
    {
        if (is_array($value)) return array_values(array_filter($value));
        if (is_string($value) && strlen($value)) return [trim($value)];
        return [];
    }

    protected function isInstagramAspectRatioOk(string $path, string $type = 'image'): bool
    {
        // Allowed ratios for feed: between 4:5 (0.8) and 1.91:1 (~1.91)
        $min = 0.8; $max = 1.91;
        $w = null; $h = null;
        if ($type === 'image') {
            $info = @getimagesize($path);
            if (is_array($info)) { $w = $info[0] ?? null; $h = $info[1] ?? null; }
        } else {
            $which = trim((string) @shell_exec('command -v ffprobe 2>/dev/null'));
            if ($which !== '') {
                $cmd = sprintf('ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 %s 2>/dev/null', escapeshellarg($path));
                $out = @shell_exec($cmd);
                if ($out) {
                    [$wStr, $hStr] = array_pad(explode('x', trim($out)), 2, null);
                    $w = is_numeric($wStr) ? (int)$wStr : null;
                    $h = is_numeric($hStr) ? (int)$hStr : null;
                }
            }
        }
        if (!$w || !$h) {
            // If unknown, don't block
            return true;
        }
        $ratio = $w / max(1, $h);
        return $ratio >= $min && $ratio <= $max;
    }

    protected function retryHttp()
    {
        return Http::retry(3, 1000, throw: false);
    }

    protected function assertOk($response, string $message): void
    {
        if (!$response->successful()) {
            throw new \RuntimeException($message . ': ' . $response->body());
        }
    }
}
