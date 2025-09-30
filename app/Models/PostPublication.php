<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostPublication extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'platform',
        'status',
        'external_id',
        'platform_url',
        'response_data',
        'error_message',
        'published_at',
        'scheduled_for',
        'retry_count',
    ];

    protected $casts = [
        'response_data' => 'array',
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'gray',
            'scheduled' => 'blue', 
            'publishing' => 'yellow',
            'published' => 'green',
            'failed' => 'red',
            default => 'gray'
        };
    }

    public function getPlatformIconAttribute()
    {
        return match($this->platform) {
            'facebook' => 'fab-facebook',
            'instagram' => 'fab-instagram', 
            'telegram' => 'fab-telegram-plane',
            default => 'fas-share-alt'
        };
    }

    public function markAsPublishing()
    {
        $this->update(['status' => 'publishing']);
        $this->post->refreshPublicationCounts();
    }

    public function markAsPublished($externalId = null, $platformUrl = null, $responseData = null)
    {
        $this->update([
            'status' => 'published',
            'external_id' => $externalId,
            'platform_url' => $platformUrl,
            'response_data' => $responseData,
            'published_at' => now(),
            'error_message' => null,
        ]);
        $this->post->refreshPublicationCounts();
    }

    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
        $this->post->refreshPublicationCounts();
    }

    public function canRetry()
    {
        return $this->status === 'failed' && $this->retry_count < 3;
    }

    public function isReadyToPublish()
    {
        return $this->status === 'scheduled' && 
               $this->scheduled_for && 
               $this->scheduled_for->isPast();
    }

    /**
     * Retry a failed publication
     */
    public function retry(): bool
    {
        if (!$this->canRetry()) {
            return false;
        }

        // Reset status and clear error message
        $this->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        // Dispatch the publication job
        \App\Jobs\PublishPostJob::dispatch($this->post, [$this->platform]);

        return true;
    }
}
