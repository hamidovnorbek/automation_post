<?php

namespace App\Filament\Resources\Posts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Jobs\PublishPostJob;
use App\Services\SocialMediaPublisher;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'secondary' => 'draft',
                        'info' => 'scheduled',
                        'warning' => 'publishing',
                        'success' => 'published',
                        'danger' => 'failed',
                    ])
                    ->icons([
                        'heroicon-s-document-text' => 'draft',
                        'heroicon-s-clock' => 'scheduled',
                        'heroicon-s-arrow-path' => 'publishing',
                        'heroicon-s-check-circle' => 'published',
                        'heroicon-s-x-circle' => 'failed',
                    ])
                    ->sortable(),

                TextColumn::make('platforms')
                    ->label('Platforms')
                    ->formatStateUsing(function ($record) {
                        if (!$record->social_medias) return 'None';
                        return collect($record->social_medias)
                            ->map(fn($platform) => ucfirst($platform))
                            ->join(', ');
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(function ($record) {
                        if ($record->total_platforms === 0) return 'No platforms';
                        return "{$record->published_platforms}/{$record->total_platforms}";
                    })
                    ->color(function ($record) {
                        if ($record->total_platforms === 0) return 'gray';
                        $percentage = $record->total_platforms > 0 ? $record->published_platforms / $record->total_platforms : 0;
                        return match(true) {
                            $percentage === 1.0 => 'success',
                            $percentage > 0 => 'warning',
                            default => 'gray'
                        };
                    })
                    ->badge(),

                TextColumn::make('schedule_time')
                    ->label('Scheduled')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->color('info')
                    ->placeholder('Immediate'),

                TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'publishing' => 'Publishing',
                        'published' => 'Published',
                        'failed' => 'Failed',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->color('warning'),
                    
                    Action::make('publish')
                        ->label('Publish Now')
                        ->icon('heroicon-s-paper-airplane')
                        ->color('success')
                        ->visible(fn ($record) => in_array($record->status, ['draft', 'failed']))
                        ->requiresConfirmation()
                        ->modalHeading('Publish Post Now')
                        ->modalDescription('Are you sure you want to publish this post immediately to all selected platforms?')
                        ->action(function ($record) {
                            PublishPostJob::dispatch($record);
                            
                            Notification::make()
                                ->title('Publication Started')
                                ->body('The post is being published to selected platforms.')
                                ->success()
                                ->send();
                        }),
                    
                    Action::make('retry')
                        ->label('Retry Failed')
                        ->icon('heroicon-s-arrow-path')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === 'failed' && $record->failed_platforms > 0)
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $publisher = app(SocialMediaPublisher::class);
                            $results = $publisher->retryFailedPublications($record);
                            
                            $successCount = count(array_filter($results));
                            $totalCount = count($results);
                            
                            Notification::make()
                                ->title('Retry Completed')
                                ->body("Retried {$totalCount} platforms, {$successCount} succeeded.")
                                ->success()
                                ->send();
                        }),
                    
                    DeleteAction::make()
                        ->color('danger'),
                ])
                    ->label('Actions')
                    ->color('primary')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    
                    Action::make('publish_selected')
                        ->label('Publish Selected')
                        ->icon('heroicon-s-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (in_array($record->status, ['draft', 'failed'])) {
                                    PublishPostJob::dispatch($record);
                                    $count++;
                                }
                            }
                            
                            Notification::make()
                                ->title('Bulk Publication Started')
                                ->body("{$count} posts are being published.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }
}
