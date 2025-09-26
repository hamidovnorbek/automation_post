<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    ];

    protected $casts = [
        'body' => 'array',
        'photos' => 'array',
        'videos' => 'array',
        'social_medias' => 'array',
        'schedule_time' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($post) {
            self::sendWebhook($post, 'created');
        });

        static::updated(function ($post) {
            self::sendWebhook($post, 'updated');
        });
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

    public function socials()
    {
        return $this->hasMany(PostSocial::class);
    }

}
