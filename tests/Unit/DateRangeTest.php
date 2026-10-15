<?php

use Abdiwaahid\FilamentLogger\Support\DateRange;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('resolves the presets relative to now', function (string $preset, string $start, string $end) {
    [$from, $until] = DateRange::resolve($preset);

    expect($from->toDateTimeString())->toBe($start)
        ->and($until->toDateTimeString())->toBe($end);
})->with([
    'today' => ['today', '2026-10-15 00:00:00', '2026-10-15 23:59:59'],
    'yesterday' => ['yesterday', '2026-10-14 00:00:00', '2026-10-14 23:59:59'],
    'last 7 days' => ['last_7_days', '2026-10-09 00:00:00', '2026-10-15 23:59:59'],
    'last 30 days' => ['last_30_days', '2026-09-16 00:00:00', '2026-10-15 23:59:59'],
    'this month' => ['this_month', '2026-10-01 00:00:00', '2026-10-31 23:59:59'],
]);

it('has no bounds without a preset or explicit dates', function () {
    expect(DateRange::resolve())->toBe([null, null])
        ->and(DateRange::resolve('not-a-preset'))->toBe([null, null]);
});

it('lets explicit dates override the matching preset bound', function () {
    [$from, $until] = DateRange::resolve('last_7_days', '2026-10-01', null);

    expect($from->toDateTimeString())->toBe('2026-10-01 00:00:00')
        ->and($until->toDateTimeString())->toBe('2026-10-15 23:59:59');
});

it('supports an open-ended range with only one explicit date', function () {
    [$from, $until] = DateRange::resolve(null, null, '2026-10-05');

    expect($from)->toBeNull()
        ->and($until->toDateTimeString())->toBe('2026-10-05 23:59:59');
});

it('adds one where clause per bound to the query', function () {
    $both = DateRange::apply(Activity::query(), ['preset' => 'today']);
    $none = DateRange::apply(Activity::query(), []);

    expect(array_map('strval', $both->getBindings()))->toBe(['2026-10-15 00:00:00', '2026-10-15 23:59:59'])
        ->and($none->getBindings())->toBe([]);
});
