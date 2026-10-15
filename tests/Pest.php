<?php

use Abdiwaahid\FilamentLogger\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

uses(TestCase::class)->in(__DIR__);

/**
 * Create an activity log row with sensible defaults.
 *
 * @param  array<string, mixed>  $attributes
 */
function createActivity(array $attributes = []): Activity
{
    return Activity::query()->create(array_merge([
        'log_name' => 'Resource',
        'description' => 'Something happened',
        'event' => 'Created',
    ], $attributes));
}

/**
 * Call a protected static method of the activity resource.
 */
function resourceOptions(string $method): array
{
    return (new ReflectionMethod(\Abdiwaahid\FilamentLogger\Resources\ActivityResource::class, $method))->invoke(null);
}
