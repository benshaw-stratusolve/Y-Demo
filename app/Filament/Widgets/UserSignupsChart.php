<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class UserSignupsChart extends ChartWidget
{
    protected ?string $heading = 'User Signups';

    protected static bool $isDiscovered = false;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $data = collect(range(11, 0))
            ->map(fn ($weeks) => User::whereBetween('created_at', [
                today()->subWeeks($weeks)->startOfWeek(),
                today()->subWeeks($weeks)->endOfWeek(),
            ])->count());

        $labels = collect(range(11, 0))
            ->map(fn ($weeks) => today()->subWeeks($weeks)->format('M d'));

        return [
            'datasets' => [
                [
                    'label' => 'Signups',
                    'data' => $data->toArray(),
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }
}
