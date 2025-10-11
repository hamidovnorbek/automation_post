<?php

namespace App\Filament\Resources\SocialAccounts;

use App\Filament\Resources\SocialAccounts\Pages\CreateSocialAccount;
use App\Filament\Resources\SocialAccounts\Pages\EditSocialAccount;
use App\Filament\Resources\SocialAccounts\Pages\ListSocialAccounts;
use App\Filament\Resources\SocialAccounts\Schemas\SocialAccountForm;
use App\Filament\Resources\SocialAccounts\Tables\SocialAccountsTable;
use App\Models\SocialAccount;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Table;

class SocialAccountResource extends Resource
{
    protected static ?string $model = SocialAccount::class;

    protected static ?string $navigationLabel = 'Connected Accounts';

    protected static ?string $modelLabel = 'Social Account';

    protected static ?string $pluralModelLabel = 'Connected Accounts';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'platform_name';

    public static function form(Schema $schema): Schema
    {
        return SocialAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialAccounts::route('/'),
            'create' => CreateSocialAccount::route('/create'),
            'edit' => EditSocialAccount::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('user_id', auth()->id())->count();
    }
}
