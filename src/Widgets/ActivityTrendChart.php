<?php

namespace Abdiwaahid\FilamentLogger\Widgets;

use Abdiwaahid\FilamentLogger\Resources\ActivityResource\Pages\ListActivities;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;

class ActivityTrendChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'Activity over time';

    protected function getTablePage(): string
    {
        return ListActivities::class;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $days): Carbon => Carbon::now()->subDays($days)->startOfDay());
        $counts = $this->getPageTableQuery()
            ->where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')
            ->groupBy('activity_date')
            ->pluck('aggregate', 'activity_date');

        return [
            'datasets' => [[
                'label' => 'Activities',
                'data' => $days->map(fn (Carbon $day): int => (int) ($counts[$day->toDateString()] ?? 0))->all(),
                'borderColor' => '#6366f1',
                'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                'fill' => true,
            ]],
            'labels' => $days->map(fn (Carbon $day): string => $day->format('M j'))->all(),
        ];
    }
}
