<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\SocialMediaPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 1;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public Post $post
    ) {}

    public function handle(SocialMediaPublisher $publisher): void
    {
        Log::info("Starting publication job for post {$this->post->id}");
        
        try {
            $results = $publisher->publishPost($this->post);
            
            $successCount = count(array_filter($results));
            $totalCount = count($results);
            
            Log::info("Publication job completed for post {$this->post->id}", [
                'success_count' => $successCount,
                'total_count' => $totalCount,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            Log::error("Publication job failed for post {$this->post->id}: " . $e->getMessage(), [
                'exception' => $e
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("PublishPostJob permanently failed for post {$this->post->id}", [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
