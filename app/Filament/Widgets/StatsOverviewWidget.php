<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', User::count()),
            Stat::make('Total Posts', Post::count()),
            Stat::make('Banned Users', User::whereNotNull('banned_at')->count()),
            Stat::make('New Users Today', User::whereDate('created_at', today())->count()),
        ];
    }
}
