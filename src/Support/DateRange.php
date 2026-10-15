<?php

namespace Abdiwaahid\FilamentLogger\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Resolves the date range used by the "Logged at" table filter.
 *
 * A preset ("today", "last_7_days", ...) provides a default range, and an explicit
 * "from" / "until" date overrides the matching bound of the preset.
 */
final class DateRange
{
    public const PRESETS = [
        'today',
        'yesterday',
        'last_7_days',
        'last_30_days',
        'this_month',
    ];

    /**
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    public static function resolve(?string $preset = null, mixed $from = null, mixed $until = null): array
    {
        $now = Carbon::now();

        [$start, $end] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default => [null, null],
        };

        if (filled($from)) {
            $start = Carbon::parse($from)->startOfDay();
        }

        if (filled($until)) {
            $end = Carbon::parse($until)->endOfDay();
        }

        return [$start, $end];
    }

    /**
     * @param  array<string, mixed>  $data  Filter state with the optional keys "preset", "from" and "until".
     */
    public static function apply(Builder $query, array $data, string $column = 'created_at'): Builder
    {
        [$start, $end] = static::resolve(
            $data['preset'] ?? null,
            $data['from'] ?? null,
            $data['until'] ?? null,
        );

        if ($start !== null) {
            $query->where($column, '>=', $start);
        }

        if ($end !== null) {
            $query->where($column, '<=', $end);
        }

        return $query;
    }
}
