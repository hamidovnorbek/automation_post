<?php

namespace App\Services\SocialMedia;

use App\Models\Post;
use App\Models\PostPublication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

abstract class AbstractSocialMediaService
{
    protected string $platform;
    protected array $config;

    public function __construct()
    {
        $this->platform = $this->getPlatformName();
        $this->config = config("services.{$this->platform}", []);
    }

    abstract protected function getPlatformName(): string;
    abstract protected function publishPost(Post $post): array;
    abstract protected function uploadMedia(string $mediaPath, string $type): array;

    public function publish(PostPublication $publication): bool
    {
        try {
            $publication->markAsPublishing();
            
            Log::info("Starting {$this->platform} publication for post {$publication->post_id}");
            
            $result = $this->publishPost($publication->post);
            
            if ($result['success']) {
                $publication->markAsPublished(
                    $result['external_id'] ?? null,
                    $result['platform_url'] ?? null,
                    $result['response_data'] ?? null
                );
                
                Log::info("Successfully published to {$this->platform} for post {$publication->post_id}");
                return true;
            } else {
                throw new \Exception($result['error'] ?? 'Unknown error occurred');
            }
        } catch (\Exception $e) {
            $errorMessage = "Failed to publish to {$this->platform}: " . $e->getMessage();
            $publication->markAsFailed($errorMessage);
            
            Log::error($errorMessage, [
                'post_id' => $publication->post_id,
                'platform' => $this->platform,
                'exception' => $e
            ]);
            
            return false;
        }
    }

    protected function getMediaUrls(Post $post): array
    {
        $mediaUrls = [];
        
        // Process photos
        if ($post->photos) {
            foreach ($post->photos as $photo) {
                $mediaUrls[] = [
                    'type' => 'photo',
                    'url' => $this->getPublicUrl($photo),
                    'path' => $photo
                ];
            }
        }
        
        // Process videos
        if ($post->videos) {
            foreach ($post->videos as $video) {
                $mediaUrls[] = [
                    'type' => 'video',
                    'url' => $this->getPublicUrl($video),
                    'path' => $video
                ];
            }
        }
        
        return $mediaUrls;
    }

    protected function getPublicUrl(string $path): string
    {
        // If using S3, create the URL manually
        if (config('filesystems.default') === 's3') {
            $s3Config = config('filesystems.disks.s3');
            $bucket = $s3Config['bucket'];
            $region = $s3Config['region'];
            return "https://{$bucket}.s3.{$region}.amazonaws.com/{$path}";
        }
        
        // For local/public disk, use asset helper
        return asset('storage/' . $path);
    }

    protected function getPostText(Post $post): string
    {
        $text = $post->title;
        
        if ($post->body && is_array($post->body)) {
            $bodyText = $this->extractTextFromRichEditor($post->body);
            if ($bodyText) {
                $text .= "\n\n" . $bodyText;
            }
        }
        
        return $text;
    }

    protected function extractTextFromRichEditor(array $content): string
    {
        $text = '';
        
        if (isset($content['content']) && is_array($content['content'])) {
            foreach ($content['content'] as $block) {
                if (isset($block['content']) && is_array($block['content'])) {
                    foreach ($block['content'] as $inline) {
                        if (isset($inline['text'])) {
                            $text .= $inline['text'];
                        }
                    }
                    $text .= "\n";
                }
            }
        }
        
        return trim($text);
    }

    protected function validateConfig(): void
    {
        $requiredFields = $this->getRequiredConfigFields();
        
        foreach ($requiredFields as $field) {
            if (empty($this->config[$field])) {
                throw new \Exception("Missing {$this->platform} configuration: {$field}");
            }
        }
    }

    protected function getRequiredConfigFields(): array
    {
        return ['access_token'];
    }
}