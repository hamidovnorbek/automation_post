<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Post Content & Publishing')
                    ->description('Create your post content and select platforms')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Enter your post title...'),

                        RichEditor::make('body')
                            ->required()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('posts/attachments')
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->placeholder('Write your post content...')
                            ->columnSpanFull(),

                        CheckboxList::make('social_medias')
                            ->label('Social Media Platforms')
                            ->helperText('Select platforms to publish to')
                            ->options([
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'telegram' => 'Telegram',
                            ])
                            ->columns(3)
                            ->required()
                            ->columnSpanFull(),

                        DateTimePicker::make('schedule_time')
                            ->label('Schedule Time')
                            ->native(false)
                            ->helperText('Leave empty to publish immediately')
                            ->minDate(now())
                            ->live()
                            ->columnSpanFull(),

                        Placeholder::make('publish_info')
                            ->label('Publication Status')
                            ->content(function ($get, $record) {
                                if (!$record) {
                                    $scheduleTime = $get('schedule_time');
                                    if ($scheduleTime) {
                                        return '🕒 This post will be scheduled for publication';
                                    }
                                    return '🚀 This post will be processed and published immediately after saving';
                                }

                                $status = match($record->status) {
                                    'draft' => '📝 Draft',
                                    'processing_media' => '⏳ Processing media files...',
                                    'ready_to_publish' => '✅ Ready to publish',
                                    'scheduled' => '🕒 Scheduled',
                                    'publishing' => '⚡ Publishing...',
                                    'published' => '✅ Published',
                                    'failed' => '❌ Failed',
                                    default => '📝 Draft'
                                };

                                $progress = $record->total_platforms > 0
                                    ? "({$record->published_platforms}/{$record->total_platforms} platforms)"
                                    : '';

                                $additionalInfo = '';
                                if ($record->status === 'processing_media') {
                                    $additionalInfo = ' - Files are being uploaded to S3 and optimized';
                                } elseif ($record->status === 'publishing') {
                                    $additionalInfo = ' - Publishing to social media platforms';
                                }

                                return "{$status} {$progress}{$additionalInfo}";
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Media Attachments')
                    ->description('Upload photos and videos for your post')
                    ->schema([
                        FileUpload::make('photos')
                            ->label('Photos')
                            ->disk('s3')
                            ->directory('photos')
                            ->visibility('public')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->preserveFilenames()
                            ->maxFiles(10)
                            ->maxSize(5120)
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file) => uniqid() . '.' . $file->getClientOriginalExtension()
                            )
                            ->columnSpanFull(),

                        FileUpload::make('videos')
                            ->label('Videos')
                            ->disk('s3')
                            ->directory('photos')
                            ->visibility('public')
                            ->multiple()
                            ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/webm'])
                            ->maxSize(102400) // ~100MB
                            ->uploadingMessage('Uploading videos...')
                            ->helperText('MP4, MOV, WEBM up to 100MB')
                            ->columnSpanFull()
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file) => uniqid() . '.' . $file->getClientOriginalExtension()
                            )

                    ])

                ]);
    }
}
