<?php

namespace App\Services\SocialMedia;

use App\Models\Post;
use Illuminate\Support\Facades\Http;

class FacebookService extends AbstractSocialMediaService
{
    protected function getPlatformName(): string
    {
        return 'facebook';
    }

    protected function getRequiredConfigFields(): array
    {
        return ['access_token', 'page_id'];
    }

    protected function publishPost(Post $post): array
    {
        $this->validateConfig();
        
        $pageId = $this->config['page_id'];
        $accessToken = $this->config['access_token'];
        $mediaUrls = $this->getMediaUrls($post);
        $message = $this->getPostText($post);
        
        try {
            if (empty($mediaUrls)) {
                return $this->publishTextPost($message, $pageId, $accessToken);
            } elseif (count($mediaUrls) === 1) {
                return $this->publishSingleMedia($mediaUrls[0], $message, $pageId, $accessToken);
            } else {
                return $this->publishMultipleMedia($mediaUrls, $message, $pageId, $accessToken);
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function publishTextPost(string $message, string $pageId, string $accessToken): array
    {
        $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/feed", [
            'message' => $message,
            'access_token' => $accessToken,
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to publish text post: ' . $response->body());
        }
        
        $postId = $response->json()['id'];
        
        return [
            'success' => true,
            'external_id' => $postId,
            'platform_url' => "https://www.facebook.com/{$postId}",
            'response_data' => $response->json()
        ];
    }

    protected function publishSingleMedia(array $media, string $message, string $pageId, string $accessToken): array
    {
        $endpoint = $media['type'] === 'photo' ? 'photos' : 'videos';
        $urlParam = $media['type'] === 'photo' ? 'url' : 'file_url';
        
        $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/{$endpoint}", [
            $urlParam => $media['url'],
            'message' => $message,
            'access_token' => $accessToken,
            'published' => true,
        ]);
        
        if (!$response->successful()) {
            throw new \Exception("Failed to publish {$media['type']}: " . $response->body());
        }
        
        $postId = $response->json()['post_id'] ?? $response->json()['id'];
        
        return [
            'success' => true,
            'external_id' => $postId,
            'platform_url' => "https://www.facebook.com/{$postId}",
            'response_data' => $response->json()
        ];
    }

    protected function publishMultipleMedia(array $mediaUrls, string $message, string $pageId, string $accessToken): array
    {
        // For multiple media, we create a batch request
        $attachedMedia = [];
        
        foreach ($mediaUrls as $media) {
            $endpoint = $media['type'] === 'photo' ? 'photos' : 'videos';
            $urlParam = $media['type'] === 'photo' ? 'url' : 'file_url';
            
            // Upload media without publishing
            $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/{$endpoint}", [
                $urlParam => $media['url'],
                'access_token' => $accessToken,
                'published' => false,
            ]);
            
            if (!$response->successful()) {
                throw new \Exception("Failed to upload {$media['type']}: " . $response->body());
            }
            
            $attachedMedia[] = [
                'media_fbid' => $response->json()['id']
            ];
        }
        
        // Publish post with attached media
        $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/feed", [
            'message' => $message,
            'attached_media' => json_encode($attachedMedia),
            'access_token' => $accessToken,
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to publish post with multiple media: ' . $response->body());
        }
        
        $postId = $response->json()['id'];
        
        return [
            'success' => true,
            'external_id' => $postId,
            'platform_url' => "https://www.facebook.com/{$postId}",
            'response_data' => $response->json()
        ];
    }

    protected function uploadMedia(string $mediaPath, string $type): array
    {
        // Media URLs are handled directly in publish methods
        return [];
    }
}