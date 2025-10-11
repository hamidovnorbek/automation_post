<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\SocialMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

    public function handle(SocialMediaService $socialMediaService): void
    {
        try {
            Log::info('Sending post to n8n with user credentials', [
                'post_id' => $this->post->id,
                'user_id' => $this->post->user_id,
                'platforms' => $this->post->social_medias
            ]);

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

            // Load user with social accounts for credential access
            $user = $this->post->user()->with('socialAccounts')->first();

            if (!$user) {
                throw new \Exception('Post user not found');
            }

            // Send post with user credentials to n8n
            $result = $socialMediaService->sendPostToN8n($postData, $user);

            if ($result['success']) {
                // Update post status to indicate successful send to n8n
                $this->post->update([
                    'status' => 'sent_to_n8n',
                    'publication_status' => array_merge($this->post->publication_status ?? [], [
                        'sent_to_n8n_at' => now()->toISOString(),
                        'n8n_response' => $result['response'] ?? null,
                    ])
                ]);

                Log::info('Post successfully sent to n8n', [
                    'post_id' => $this->post->id,
                    'user_id' => $user->id,
                    'platforms_with_credentials' => array_keys($result['credentials'] ?? [])
                ]);
            } else {
                throw new \Exception($result['error'] ?? 'Unknown error sending to n8n');
            }

        } catch (\Exception $e) {
            Log::error('Failed to send post to n8n', [
                'post_id' => $this->post->id,
                'user_id' => $this->post->user_id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Update post status to failed if all retries exhausted
            if ($this->attempts() >= $this->tries) {
                $this->post->update([
                    'status' => 'failed',
                    'publication_status' => array_merge($this->post->publication_status ?? [], [
                        'failed_at' => now()->toISOString(),
                        'error' => $e->getMessage(),
                        'attempts' => $this->attempts()
                    ])
                ]);
            }

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendToN8nJob failed permanently', [
            'post_id' => $this->post->id,
            'user_id' => $this->post->user_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Mark post as failed
        $this->post->update([
            'status' => 'failed',
            'publication_status' => array_merge($this->post->publication_status ?? [], [
                'failed_permanently_at' => now()->toISOString(),
                'final_error' => $exception->getMessage(),
                'total_attempts' => $this->attempts()
            ])
        ]);
    }
}
