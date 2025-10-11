<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'photos',
        'videos',
        'social_medias',
        'schedule_time',
        'status',
        'publication_status',
        'total_platforms',
        'published_platforms',
        'failed_platforms',
    ];

    protected $casts = [
        'body' => 'array',
        'photos' => 'array',
        'videos' => 'array',
        'social_medias' => 'array',
        'schedule_time' => 'datetime',
        'publication_status' => 'array',
    ];

    // Accessor for compatibility with services that expect 'platforms'
    public function getPlatformsAttribute()
    {
        return $this->social_medias ?? [];
    }

    // Mutator for compatibility with services that set 'platforms'
    public function setPlatformsAttribute($value)
    {
        $this->social_medias = $value;
    }

    /**
     * Check if the post has unprocessed files (Livewire temporary files)
     */
    public function hasUnprocessedFiles(): bool
    {
        $photos = $this->photos ?? [];
        $videos = $this->videos ?? [];

        // Check if any files are still temporary Livewire files
        foreach (array_merge($photos, $videos) as $file) {
            if (!str_starts_with($file, 'http') && !str_contains($file, 's3.amazonaws.com')) {
                return true; // Found a temporary file
            }
        }

        return false;
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($post) {
            // Skip n8n processing if files need processing
            if ($post->hasUnprocessedFiles()) {
                $post->update(['status' => 'processing_media']);
                \App\Jobs\ProcessMediaUploadJob::dispatch($post);
            } else {
                // Send directly to n8n if no media processing needed
                \App\Jobs\SendToN8nJob::dispatch($post);
            }
        });

        static::updated(function ($post) {
            // Optional: Send update webhook to n8n for tracking
            if ($post->wasChanged(['title', 'body', 'social_medias', 'status'])) {
                self::sendWebhook($post, 'updated');
            }
        });
    }

    public function publications()
    {
        return $this->hasMany(PostPublication::class);
    }

    public function socials()
    {
        return $this->hasMany(PostSocial::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'gray',
            'processing_media' => 'orange',
            'ready_to_publish' => 'blue',
            'sent_to_n8n' => 'purple',
            'scheduled' => 'blue',
            'publishing' => 'yellow',
            'published' => 'green',
            'failed' => 'red',
            default => 'gray'
        };
    }

    public function getProgressPercentageAttribute()
    {
        // For n8n workflow, progress is based on status
        return match($this->status) {
            'draft' => 0,
            'processing_media' => 25,
            'ready_to_publish' => 50,
            'sent_to_n8n' => 75,
            'published' => 100,
            'failed' => 0,
            default => 0
        };
    }

    public function isScheduled()
    {
        return $this->schedule_time && $this->schedule_time->isFuture();
    }

    public function isReadyToPublish()
    {
        return $this->schedule_time && $this->schedule_time->isPast() && $this->status === 'scheduled';
    }

    /**
     * Send webhook notification when post is created or updated
     */
    public static function sendWebhook($post, $action)
    {
        try {
            $webhookUrl = config('services.n8n.webhook_url');

            if (!$webhookUrl) {
                Log::warning("Webhook URL not configured, skipping webhook for post {$post->id}");
                return;
            }

            $payload = [
                'action' => $action,
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'body' => $post->body,
                    'photos' => $post->photos,
                    'videos' => $post->videos,
                    'social_medias' => $post->social_medias,
                    'schedule_time' => $post->schedule_time?->toISOString(),
                    'status' => $post->status,
                    'progress' => $post->progress_percentage,
                    'created_at' => $post->created_at->toISOString(),
                    'updated_at' => $post->updated_at->toISOString(),
                ],
                'timestamp' => now()->toISOString(),
            ];

            Http::timeout(10)
                ->post($webhookUrl, $payload);

            Log::info("Webhook sent successfully for post {$post->id} - {$action}");
        } catch (\Exception $e) {
            Log::error("Failed to send webhook for post {$post->id} - {$action}: " . $e->getMessage());
        }
    }
}
