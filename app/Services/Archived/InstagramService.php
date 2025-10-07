<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    protected string $graphUrl;

    public function __construct()
    {
        $this->graphUrl = 'https://graph.facebook.com/' . config('services.instagram.version', 'v23.0');
    }

    public function testPostPhoto(): array
    {
        try {
            $token = config('services.instagram.access_token');
            $userId = config('services.instagram.business_account_id');

            if (!$token || !$userId) {
                return [
                    'success' => false,
                    'message' => 'Instagram credentials not configured (missing access_token or business_account_id)',
                    'configured' => false
                ];
            }

            // 👉 Use your working S3 public photo URL
            $imageUrl = "https://s3iucketforinsta.s3.ap-southeast-1.amazonaws.com/photos/edu1.jpg";

            // Step 1: Create media container
            $container = Http::asForm()->post("{$this->graphUrl}/{$userId}/media", [
                'image_url'    => $imageUrl,
                'caption'      => "Testing Instagram upload from Laravel 🚀 " . now()->format('Y-m-d H:i:s'),
                'access_token' => $token,
            ]);

            if (!$container->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to create Instagram image container: ' . $container->body(),
                    'configured' => true,
                    'api_response' => $container->json()
                ];
            }

            $creationId = $container->json('id');

            // Step 2: Publish media
            $publish = Http::asForm()->post("{$this->graphUrl}/{$userId}/media_publish", [
                'creation_id'  => $creationId,
                'access_token' => $token,
            ]);

            if (!$publish->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to publish Instagram image: ' . $publish->body(),
                    'configured' => true,
                    'container_id' => $creationId,
                    'api_response' => $publish->json()
                ];
            }

            return [
                'success' => true,
                'message' => 'Instagram post published successfully',
                'configured' => true,
                'container' => $container->json(),
                'publish' => $publish->json(),
                'post_id' => $publish->json('id'),
                'image_url' => $imageUrl
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Instagram test failed: ' . $e->getMessage(),
                'configured' => true,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Publish post to Instagram with support for single image, multiple images (carousel), or video
     */
    public function publish(Post $post): array
    {
        try {
            $token = config('services.instagram.access_token');
            $userId = config('services.instagram.business_account_id');

            if (!$token || !$userId) {
                throw new \RuntimeException('Instagram credentials are not configured.');
            }

            $caption = $this->buildCaption($post);
            $photos = $this->normalizePhotos($post->photos);
            $videos = $this->normalizePhotos($post->videos);

            // Validate that we have media (Instagram requires media)
            if (empty($photos) && empty($videos)) {
                throw new \RuntimeException('Instagram requires at least one image or video');
            }

            // Handle video post (single video only)
            if (!empty($videos)) {
                return $this->publishVideo($userId, $videos[0], $caption, $token);
            }

            // Handle single image
            if (count($photos) === 1) {
                return $this->publishSingleImage($userId, $photos[0], $caption, $token);
            }

            // Handle multiple images (carousel)
            if (count($photos) > 1) {
                return $this->publishCarousel($userId, $photos, $caption, $token);
            }

            throw new \RuntimeException('No valid media found for Instagram post');

        } catch (\Exception $e) {
            throw new \RuntimeException('Instagram publishing failed: ' . $e->getMessage());
        }
    }

    /**
     * Publish single image to Instagram
     */
    private function publishSingleImage(string $userId, string $imageUrl, string $caption, string $token): array
    {
        // Create container
        $container = Http::asForm()->post("{$this->graphUrl}/{$userId}/media", [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $token,
        ]);

        if (!$container->successful()) {
            throw new \RuntimeException('Failed to create Instagram image container: ' . $container->body());
        }

        $creationId = $container->json('id');

        // Publish
        $publish = Http::asForm()->post("{$this->graphUrl}/{$userId}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);

        if (!$publish->successful()) {
            throw new \RuntimeException('Failed to publish Instagram image: ' . $publish->body());
        }

        return [
            'success' => true,
            'post_id' => $publish->json('id'),
            'type' => 'single_image',
            'media_count' => 1,
            'container_id' => $creationId
        ];
    }

    /**
     * Publish carousel (multiple images) to Instagram
     */
    private function publishCarousel(string $userId, array $photos, string $caption, string $token): array
    {
        $containerIds = [];

        // Create containers for each image
        foreach ($photos as $index => $photoUrl) {
            $container = Http::asForm()->post("{$this->graphUrl}/{$userId}/media", [
                'image_url' => $photoUrl,
                'is_carousel_item' => 'true',
                'access_token' => $token,
            ]);

            if (!$container->successful()) {
                throw new \RuntimeException("Failed to create Instagram carousel container {$index}: " . $container->body());
            }

            $containerIds[] = $container->json('id');
        }

        // Create carousel container
        $carouselContainer = Http::asForm()->post("{$this->graphUrl}/{$userId}/media", [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $containerIds),
            'caption' => $caption,
            'access_token' => $token,
        ]);

        if (!$carouselContainer->successful()) {
            throw new \RuntimeException('Failed to create Instagram carousel container: ' . $carouselContainer->body());
        }

        $carouselId = $carouselContainer->json('id');

        // Publish carousel
        $publish = Http::asForm()->post("{$this->graphUrl}/{$userId}/media_publish", [
            'creation_id' => $carouselId,
            'access_token' => $token,
        ]);

        if (!$publish->successful()) {
            throw new \RuntimeException('Failed to publish Instagram carousel: ' . $publish->body());
        }

        return [
            'success' => true,
            'post_id' => $publish->json('id'),
            'type' => 'carousel',
            'media_count' => count($photos),
            'container_ids' => $containerIds,
            'carousel_id' => $carouselId
        ];
    }

    /**
     * Publish video to Instagram
     */
    private function publishVideo(string $userId, string $videoUrl, string $caption, string $token): array
    {
        // Create video container
        $container = Http::asForm()->post("{$this->graphUrl}/{$userId}/media", [
            'video_url' => $videoUrl,
            'media_type' => 'VIDEO',
            'caption' => $caption,
            'access_token' => $token,
        ]);

        if (!$container->successful()) {
            throw new \RuntimeException('Failed to create Instagram video container: ' . $container->body());
        }

        $creationId = $container->json('id');

        // Wait for video processing (Instagram requires this)
        $this->waitForVideoProcessing($creationId, $token);

        // Publish
        $publish = Http::asForm()->post("{$this->graphUrl}/{$userId}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);

        if (!$publish->successful()) {
            throw new \RuntimeException('Failed to publish Instagram video: ' . $publish->body());
        }

        return [
            'success' => true,
            'post_id' => $publish->json('id'),
            'type' => 'video',
            'media_count' => 1,
            'container_id' => $creationId
        ];
    }

    /**
     * Wait for Instagram video processing to complete
     */
    private function waitForVideoProcessing(string $containerId, string $token, int $maxWaitTime = 60): void
    {
        $startTime = time();
        
        while ((time() - $startTime) < $maxWaitTime) {
            $status = Http::get("{$this->graphUrl}/{$containerId}", [
                'fields' => 'status_code',
                'access_token' => $token,
            ]);

            if ($status->successful()) {
                $statusCode = $status->json('status_code');
                if ($statusCode === 'FINISHED') {
                    return;
                } elseif ($statusCode === 'ERROR') {
                    throw new \RuntimeException('Instagram video processing failed');
                }
            }

            sleep(2); // Wait 2 seconds before checking again
        }

        throw new \RuntimeException('Instagram video processing timeout');
    }

    /**
     * Build caption for Instagram post
     */
    private function buildCaption(Post $post): string
    {
        $title = trim((string) ($post->title ?? ''));
        $body = is_array($post->body) ? implode("\n", $post->body) : (string) $post->body;
        $bodyText = strip_tags($body);
        
        $caption = trim($title . (strlen($title) && strlen($bodyText) ? "\n\n" : '') . $bodyText);
        
        // Instagram caption limit is 2200 characters
        if (strlen($caption) > 2200) {
            $caption = substr($caption, 0, 2195) . '...';
        }
        
        return $caption;
    }

    /**
     * Normalize photos array and convert to S3 URLs
     */
    private function normalizePhotos($photos): array
    {
        if (empty($photos)) return [];
        
        $photos = is_array($photos) ? $photos : [$photos];
        $s3BaseUrl = rtrim(config('app.url'), '/') . '/storage/';
        
        return array_map(function ($photo) use ($s3BaseUrl) {
            // If already a full URL, return as-is
            if (str_starts_with($photo, 'http')) {
                return $photo;
            }
            
            // Convert to S3 public URL
            $s3Url = "https://" . config('filesystems.disks.s3.bucket') . ".s3." . 
                     config('filesystems.disks.s3.region') . ".amazonaws.com/" . ltrim($photo, '/');
            
            return $s3Url;
        }, array_filter($photos));
    }
}
