<?php

namespace App\Filament\Resources\SocialAccounts\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SocialAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->schema([
                        Hidden::make('user_id')
                            ->default(fn () => auth()->id()),

                        Select::make('platform_name')
                            ->label('Platform')
                            ->options([
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'telegram' => 'Telegram',
                                'youtube' => 'YouTube',
                            ])
                            ->required()
                            ->native(false)
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('user_id', auth()->id());
                            }),

                        TextInput::make('account_username')
                            ->label('Username/Handle')
                            ->placeholder('e.g., @username')
                            ->maxLength(255),
                    ]),

                Section::make('Authentication')
                    ->schema([
                        Textarea::make('access_token')
                            ->label('Access Token')
                            ->required()
                            ->placeholder('Enter the access token or API key')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('refresh_token')
                            ->label('Refresh Token (Optional)')
                            ->placeholder('Enter the refresh token if available')
                            ->rows(2)
                            ->columnSpanFull(),

                        DateTimePicker::make('expires_at')
                            ->label('Token Expiry Date')
                            ->placeholder('Select when the token expires')
                            ->timezone('UTC'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Disable to temporarily stop using this account'),
                    ]),
            ]);
    }
}
