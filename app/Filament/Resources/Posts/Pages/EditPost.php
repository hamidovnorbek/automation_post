<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Http;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $post = $this->record;

        Http::post('http://localhost:5678/webhook/social-post', [
            'title' => $post->title,
            'body' => $post->body,
            'photos' => $post->photos,
            'videos' => $post->videos,
            'social_medias' => $post->social_medias,
        ]);
    }

}
