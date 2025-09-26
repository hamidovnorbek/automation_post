<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSocial extends Model
{
    protected $fillable = [
        'post_id',
        'platform',
        'status',
        'response',
    ];


    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
