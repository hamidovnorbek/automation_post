<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendToN8nJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public Post $post
    ) {
        // Use default queue to avoid queue configuration issues
        $this->onQueue('default');
    }

    public function handle(): void
    {
        try {

            Log::info('Sending post to n8n', ['post_id' => $this->post->id]);

            // Prepare post data for n8n
            $postData = [
                'id' => $this->post->id,
                'title' => $this->post->title,
                'body' => $this->post->body,
                'photos' => $this->post->photos ?? [],
                'videos' => $this->post->videos ?? [],
                'social_medias' => $this->post->social_medias ?? [],
                'schedule_time' => $this->post->schedule_time?->toISOString(),
                'status' => $this->post->status,
                'created_at' => $this->post->created_at->toISOString(),
                'updated_at' => $this->post->updated_at->toISOString(),
            ];

            // Get n8n webhook URL from environment
            $n8nWebhookUrl = config('services.n8n.webhook_url');
            
            if (!$n8nWebhookUrl) {
                throw new \Exception('n8n webhook URL not configured');
            }

            Log::info('Sending HTTP request to n8n', [
                'url' => $n8nWebhookUrl,
                'post_id' => $this->post->id
            ]);

            // Send POST request to n8n
            $response = Http::timeout(30)
                ->retry(2, 1000) // Retry 2 times with 1 second delay
                ->post($n8nWebhookUrl, [
                    'action' => 'publish_post',
                    'post' => $postData,
                    'timestamp' => now()->toISOString(),
                ]);

            if ($response->successful()) {
                // Update post status only if post exists in database
                if ($this->post->exists) {
                    $this->post->update([
                        'status' => 'sent_to_n8n',
                        'publication_status' => [
                            'sent_to_n8n_at' => now()->toISOString(),
                            'n8n_response' => $response->json()
                        ]
                    ]);
                }

                Log::info('Post sent to n8n successfully', [
                    'post_id' => $this->post->id,
                    'response_status' => $response->status(),
                    'response_body' => $response->json()
                ]);
            } else {
                throw new \Exception("n8n webhook failed with status {$response->status()}: {$response->body()}");
            }

        } catch (\Exception $e) {
            Log::error('Failed to send post to n8n', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage()
            ]);

            // Only update database if post exists
            if ($this->post->exists) {
                $this->post->update([
                    'status' => 'failed',
                    'publication_status' => [
                        'error' => 'Failed to send to n8n: ' . $e->getMessage(),
                        'failed_at' => now()->toISOString()
                    ]
                ]);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendToN8nJob permanently failed', [
            'post_id' => $this->post->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Only update database if post exists
        if ($this->post->exists) {
            $this->post->update([
                'status' => 'failed',
                'publication_status' => [
                    'error' => 'Job permanently failed: ' . $exception->getMessage(),
                    'failed_at' => now()->toISOString()
                ]
            ]);
        }
    }
}