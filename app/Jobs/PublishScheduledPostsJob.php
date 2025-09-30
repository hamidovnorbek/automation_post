<?php

namespace App\Jobs;

use App\Services\SocialMediaPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishScheduledPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes

    public function handle(SocialMediaPublisher $publisher): void
    {
        Log::info('Starting scheduled posts publication job');
        
        try {
            $publishedCount = $publisher->publishScheduledPosts();
            
            Log::info("Scheduled posts publication completed", [
                'published_count' => $publishedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Scheduled posts publication job failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PublishScheduledPostsJob permanently failed', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
