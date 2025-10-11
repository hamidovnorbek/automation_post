<?php

namespace App\Filament\Resources\SocialAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SocialAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', Auth::id()))
            ->columns([
                TextColumn::make('platform_name')
                    ->label('Platform')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'telegram' => 'Telegram',
                        'youtube' => 'YouTube',
                        default => ucfirst($state),
                    })
                    ->icon(fn (string $state): string => match($state) {
                        'facebook' => 'heroicon-s-globe-alt',
                        'instagram' => 'heroicon-s-camera',
                        'telegram' => 'heroicon-s-chat-bubble-left',
                        'youtube' => 'heroicon-s-play',
                        default => 'heroicon-s-link',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('account_username')
                    ->label('Username')
                    ->placeholder('Not set')
                    ->searchable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn ($record) => $record->getStatusLabel())
                    ->color(fn ($record) => $record->getStatusColor()),

                TextColumn::make('expires_at')
                    ->label('Token Expires')
                    ->dateTime('M j, Y g:i A')
                    ->placeholder('Never')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Connected')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('platform_name')
                    ->label('Platform')
                    ->options([
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'telegram' => 'Telegram',
                        'youtube' => 'YouTube',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All accounts')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                TernaryFilter::make('expired')
                    ->label('Token Status')
                    ->placeholder('All tokens')
                    ->trueLabel('Expired tokens')
                    ->falseLabel('Valid tokens')
                    ->queries(
                        true: fn (Builder $query) => $query->where('expires_at', '<', now()),
                        false: fn (Builder $query) => $query->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        }),
                    ),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No social accounts connected')
            ->emptyStateDescription('Connect your social media accounts to start publishing posts automatically. Visit /social/connections to get started.')
            ->emptyStateIcon('heroicon-o-link');
    }
}
