<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'photos' => 'array',
        'videos' => 'array',
        'social_medias' => 'array',
        'schedule_time' => 'datetime',
    ];

    public function socials()
    {
        return $this->hasMany(PostSocial::class);
    }

}
