<?php

namespace App\Services\SocialMedia;

use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService extends AbstractSocialMediaService
{
    protected function getPlatformName(): string
    {
        return 'instagram';
    }

    protected function getRequiredConfigFields(): array
    {
        return ['access_token', 'business_account_id'];
    }

    protected function publishPost(Post $post): array
    {
        $this->validateConfig();
        
        $mediaUrls = $this->getMediaUrls($post);
        $caption = $this->getPostText($post);
        
        try {
            if (empty($mediaUrls)) {
                // Text-only post (not supported by Instagram)
                throw new \Exception('Instagram requires at least one image or video');
            }
            
            if (count($mediaUrls) === 1) {
                return $this->publishSingleMedia($mediaUrls[0], $caption);
            } else {
                return $this->publishCarousel($mediaUrls, $caption);
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function publishSingleMedia(array $media, string $caption): array
    {
        $businessAccountId = $this->config['business_account_id'];
        $accessToken = $this->config['access_token'];
        
        // Step 1: Create media container
        $containerResponse = Http::post("https://graph.facebook.com/v18.0/{$businessAccountId}/media", [
            'image_url' => $media['url'],
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);
        
        if (!$containerResponse->successful()) {
            throw new \Exception('Failed to create media container: ' . $containerResponse->body());
        }
        
        $containerId = $containerResponse->json()['id'];
        
        // Step 2: Publish the container
        $publishResponse = Http::post("https://graph.facebook.com/v18.0/{$businessAccountId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);
        
        if (!$publishResponse->successful()) {
            throw new \Exception('Failed to publish media: ' . $publishResponse->body());
        }
        
        $mediaId = $publishResponse->json()['id'];
        
        return [
            'success' => true,
            'external_id' => $mediaId,
            'platform_url' => "https://www.instagram.com/p/{$mediaId}",
            'response_data' => $publishResponse->json()
        ];
    }

    protected function publishCarousel(array $mediaUrls, string $caption): array
    {
        $businessAccountId = $this->config['business_account_id'];
        $accessToken = $this->config['access_token'];
        
        $containerIds = [];
        
        // Step 1: Create containers for each media item
        foreach ($mediaUrls as $media) {
            $params = [
                'access_token' => $accessToken,
                'is_carousel_item' => true,
            ];
            
            if ($media['type'] === 'photo') {
                $params['image_url'] = $media['url'];
            } else {
                $params['video_url'] = $media['url'];
                $params['media_type'] = 'VIDEO';
            }
            
            $response = Http::post("https://graph.facebook.com/v18.0/{$businessAccountId}/media", $params);
            
            if (!$response->successful()) {
                throw new \Exception('Failed to create carousel item: ' . $response->body());
            }
            
            $containerIds[] = $response->json()['id'];
        }
        
        // Step 2: Create carousel container
        $carouselResponse = Http::post("https://graph.facebook.com/v18.0/{$businessAccountId}/media", [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $containerIds),
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);
        
        if (!$carouselResponse->successful()) {
            throw new \Exception('Failed to create carousel: ' . $carouselResponse->body());
        }
        
        $carouselId = $carouselResponse->json()['id'];
        
        // Step 3: Publish the carousel
        $publishResponse = Http::post("https://graph.facebook.com/v18.0/{$businessAccountId}/media_publish", [
            'creation_id' => $carouselId,
            'access_token' => $accessToken,
        ]);
        
        if (!$publishResponse->successful()) {
            throw new \Exception('Failed to publish carousel: ' . $publishResponse->body());
        }
        
        $mediaId = $publishResponse->json()['id'];
        
        return [
            'success' => true,
            'external_id' => $mediaId,
            'platform_url' => "https://www.instagram.com/p/{$mediaId}",
            'response_data' => $publishResponse->json()
        ];
    }

    protected function uploadMedia(string $mediaPath, string $type): array
    {
        // For Instagram, we use direct URLs, so this method is not needed
        // Media should already be uploaded to S3 or accessible publicly
        return [];
    }
}