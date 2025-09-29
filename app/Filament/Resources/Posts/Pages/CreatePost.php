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

        dispatch(new \App\Jobs\PublishPostJob($post));

    }

}
