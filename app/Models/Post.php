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
            // Skip publication record creation if files need processing
            if ($post->hasUnprocessedFiles()) {
                $post->update(['status' => 'processing_media']);
                \App\Jobs\ProcessMediaUploadJob::dispatch($post);
            } else {
                $post->createPublicationRecords();
                self::sendWebhook($post, 'created');
            }
        });

        static::updated(function ($post) {
            if ($post->wasChanged('social_medias')) {
                $post->syncPublicationRecords();
            }
            self::sendWebhook($post, 'updated');
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

    public function createPublicationRecords()
    {
        if (empty($this->social_medias)) return;

        foreach ($this->social_medias as $platform) {
            $this->publications()->create([
                'platform' => $platform,
                'status' => $this->schedule_time ? 'scheduled' : 'pending',
                'scheduled_for' => $this->schedule_time,
            ]);
        }

        $this->update([
            'total_platforms' => count($this->social_medias),
            'status' => $this->schedule_time ? 'scheduled' : 'draft'
        ]);
    }

    public function syncPublicationRecords()
    {
        // Remove publications for deselected platforms
        $currentPlatforms = $this->social_medias ?? [];
        $this->publications()->whereNotIn('platform', $currentPlatforms)->delete();

        // Add publications for new platforms
        $existingPlatforms = $this->publications()->pluck('platform')->toArray();
        $newPlatforms = array_diff($currentPlatforms, $existingPlatforms);

        foreach ($newPlatforms as $platform) {
            $this->publications()->create([
                'platform' => $platform,
                'status' => $this->schedule_time ? 'scheduled' : 'pending',
                'scheduled_for' => $this->schedule_time,
            ]);
        }

        $this->refreshPublicationCounts();
    }

    public function refreshPublicationCounts()
    {
        $published = $this->publications()->where('status', 'published')->count();
        $failed = $this->publications()->where('status', 'failed')->count();
        $total = $this->publications()->count();

        $status = 'draft';
        if ($total > 0) {
            if ($published == $total) {
                $status = 'published';
            } elseif ($published > 0 || $failed > 0) {
                $status = 'publishing';
            } elseif ($this->schedule_time && $this->schedule_time->isFuture()) {
                $status = 'scheduled';
            }
        }

        $this->update([
            'total_platforms' => $total,
            'published_platforms' => $published,
            'failed_platforms' => $failed,
            'status' => $status,
        ]);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'gray',
            'processing_media' => 'orange',
            'ready_to_publish' => 'blue',
            'scheduled' => 'blue',
            'publishing' => 'yellow',
            'published' => 'green',
            'failed' => 'red',
            default => 'gray'
        };
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->total_platforms === 0) return 0;
        return round(($this->published_platforms / $this->total_platforms) * 100);
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
                ->post('http://localhost:5678/webhook/social-post', $payload);

            Log::info("Webhook sent successfully for post {$post->id} - {$action}");
        } catch (\Exception $e) {
            Log::error("Failed to send webhook for post {$post->id} - {$action}: " . $e->getMessage());
        }
    }
}
