<?php

namespace App\Filament\Widgets;

use App\Models\SocialAccount;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class SocialConnectionsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $user = Auth::user();
        $connections = $user->socialAccounts;
        
        $active = $connections->where('is_active', true)->count();
        $expired = $connections->filter(fn($conn) => $conn->isExpired())->count();
        $expiringSoon = $connections->filter(fn($conn) => $conn->isExpiringSoon())->count();
        
        return [
            Stat::make('Connected Accounts', $active)
                ->description('Active social media connections')
                ->descriptionIcon('heroicon-m-link')
                ->color($active > 0 ? 'success' : 'gray')
                ->url(route('social.connections')),
                
            Stat::make('Expiring Soon', $expiringSoon)
                ->description('Tokens expiring within 60 minutes')
                ->descriptionIcon('heroicon-m-clock')
                ->color($expiringSoon > 0 ? 'warning' : 'success'),
                
            Stat::make('Expired Tokens', $expired)
                ->description('Connections requiring re-authentication')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($expired > 0 ? 'danger' : 'success'),
        ];
    }
}