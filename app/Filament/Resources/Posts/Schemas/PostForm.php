<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms;
//use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Post Content & Publishing')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

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
                            ->columnSpanFull(),

                        CheckboxList::make('social_medias')
                            ->label('Social Media Platforms')
                            ->options([
                                'facebook' => 'Facebook',
                                'twitter' => 'Twitter/X',
                                'instagram' => 'Instagram',
                                'linkedin' => 'LinkedIn',
                                'tiktok' => 'TikTok',
                                'youtube' => 'YouTube',
                                'telegram' => 'Telegram',
                            ])
                            ->columns(2)
                            ->columnSpanFull(),

                        DateTimePicker::make('schedule_time')
                            ->label('Schedule Time')
                            ->native(false)
                            ->timezone('UTC')
                            ->helperText('Leave empty to publish immediately')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Media')
                    ->schema([
                        FileUpload::make('photos')
                            ->multiple()
                            ->image()
                            ->reorderable()
                            ->maxFiles(10)
                            ->directory('posts/photos')
                            ->columnSpanFull(),

                        FileUpload::make('videos')
                            ->multiple()
                            ->acceptedFileTypes(['video/mp4', 'video/avi', 'video/mov', 'video/wmv'])
                            ->maxFiles(5)
                            ->directory('posts/videos')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }
}
