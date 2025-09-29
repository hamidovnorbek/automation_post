<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSocial extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_POSTED = 'posted';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'post_id',
        'platform',
        'status',
        'response',
    ];

    protected $casts = [
        'response' => 'array',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
