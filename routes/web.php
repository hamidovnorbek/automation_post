<?php

use Illuminate\Support\Facades\Route;
use App\Models\Post;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-webhook', function () {
    $post = Post::first();
    if ($post) {
        Post::sendWebhook($post, 'manual_test');
        return response()->json([
            'status' => 'Webhook test sent',
            'post_id' => $post->id
        ]);
    }
    return response()->json(['status' => 'No posts found']);
});
