<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Handle the form submission with immediate feedback
     */



    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ Fix paths for photos
        if (!empty($data['photos'])) {
            $data['photos'] = collect($data['photos'])
                ->map(function ($path) {
                    $path = ltrim($path, '/'); // remove leading slash
                    return Storage::disk('s3')->url($path);
                })
                ->toArray();
        }

        // ✅ Fix paths for videos
        if (!empty($data['videos'])) {
            $data['videos'] = collect($data['videos'])
                ->map(function ($path) {
                    $path = ltrim($path, '/');
                    return Storage::disk('s3')->url($path);
                })
                ->toArray();
        }

//        dd($data);

        return $data;
    }
    protected function handleRecordCreation(array $data): Post
    {
        // Show immediate feedback
        Notification::make()
            ->title('Post Created Successfully!')
            ->body('Your post is being processed. Media files are being uploaded to S3 and optimized.')
            ->success()
            ->duration(5000)
            ->send();

        // Create the record quickly
        $record = parent::handleRecordCreation($data);

        // Additional notification for media processing
        if ($record->hasUnprocessedFiles()) {
            Notification::make()
                ->title('Media Processing Started')
                ->body('Your images and videos are being uploaded to S3. You can safely navigate away - publishing will continue in the background.')
                ->info()
                ->duration(8000)
                ->send();
        }

        return $record;
    }

    /**
     * Get the form actions
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Create & Publish Post')
                ->icon('heroicon-o-rocket-launch'),
            $this->getCreateAnotherFormAction()
                ->label('Create & Create Another')
                ->icon('heroicon-o-plus'),
            $this->getCancelFormAction()
                ->label('Cancel')
                ->icon('heroicon-o-x-mark'),
        ];
    }

    /**
     * Customize the create action
     */
    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->action(function () {
                $this->closeActionModal();
                $this->create();
            })
            ->extraAttributes([
                'wire:loading.attr' => 'disabled',
                'wire:loading.class' => 'opacity-50 cursor-not-allowed',
            ]);
    }

    /**
     * Get the header actions
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('quick_tips')
                ->label('Quick Tips')
                ->icon('heroicon-o-light-bulb')
                ->color('info')
                ->action(function () {
                    Notification::make()
                        ->title('Social Media Post Tips')
                        ->body('• Instagram requires at least one image or video<br/>• Multiple images will create an Instagram carousel<br/>• Files are automatically uploaded to S3<br/>• Publishing happens in the background')
                        ->info()
                        ->duration(10000)
                        ->send();
                }),
        ];
    }

    /**
     * Redirect after creation
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
