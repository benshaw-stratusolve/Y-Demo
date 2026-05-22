<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $bannedCount = User::whereNotNull('banned_at')->count();
        $newToday = User::whereDate('created_at', today())->count();

        $postChart = collect(range(13, 0))
            ->map(fn ($days) => Post::whereDate('created_at', today()->subDays($days))->count())
            ->toArray();

        $userGrowth = collect(range(13, 0))
            ->map(fn ($days) => User::whereDate('created_at', today()->subDays($days))->count())
            ->toArray();

        $hourlyUsers = collect(range(0, 23))
            ->map(function ($hour) {
                $start = today()->startOfDay()->addHours($hour);
                $end = $start->copy()->addHour();

                return User::whereBetween('created_at', [$start, $end])->count();
            })
            ->toArray();

        return [
            Stat::make('Total Users', number_format(User::count()))
                ->description($newToday.' new today')
                ->descriptionIcon('heroicon-m-user-plus')
                ->descriptionColor('success')
                ->chart($userGrowth)
                ->color('primary')
                ->url('/admin/users'),

            Stat::make('Total Posts', number_format(Post::count()))
                ->description('Across all users')
                ->descriptionIcon('heroicon-m-document-text')
                ->descriptionColor('info')
                ->chart($postChart)
                ->color('info')
                ->url('/admin/posts'),

            Stat::make('Banned Users', number_format($bannedCount))
                ->description($bannedCount > 0 ? 'Accounts restricted' : 'No active bans')
                ->descriptionIcon($bannedCount > 0 ? 'heroicon-m-no-symbol' : 'heroicon-m-check-circle')
                ->descriptionColor($bannedCount > 0 ? 'danger' : 'success')
                ->color($bannedCount > 0 ? 'danger' : 'success'),

            Stat::make('New Users Today', number_format($newToday))
                ->description($newToday > 0 ? 'Joined since midnight' : 'None yet today')
                ->descriptionIcon($newToday > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->descriptionColor($newToday > 0 ? 'success' : 'gray')
                ->chart($hourlyUsers)
                ->color($newToday > 0 ? 'success' : 'gray'),
        ];
    }
}
