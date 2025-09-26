<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Http;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;
    protected function afterCreate(): void
    {
        $post = $this->record;

        // n8n webhookga yuborish
        Http::post('http://localhost:5678/webhook/social-post', [
            'title' => $post->title,
            'body' => $post->body,
            'photos' => $post->photos,
            'videos' => $post->videos,
            'social_medias' => $post->social_medias,
        ]);
    }

}
