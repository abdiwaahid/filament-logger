<?php

use Abdiwaahid\FilamentLogger\Resources\ActivityResource\Pages\ListActivities;
use Abdiwaahid\FilamentLogger\Tests\Fixtures\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Alice Admin',
        'email' => 'alice@example.test',
        'password' => 'secret',
    ]);

    $this->actingAs($this->user);
});

it('filters by one or more log names', function () {
    $access = createActivity(['log_name' => 'Access', 'event' => 'Login']);
    $model = createActivity(['log_name' => 'Model']);
    $resource = createActivity(['log_name' => 'Resource']);

    Livewire::test(ListActivities::class)
        ->filterTable('log_name', ['Access', 'Model'])
        ->assertCanSeeTableRecords([$access, $model])
        ->assertCanNotSeeTableRecords([$resource]);
});

it('filters by event', function () {
    $created = createActivity(['event' => 'Created']);
    $deleted = createActivity(['event' => 'Deleted']);

    Livewire::test(ListActivities::class)
        ->filterTable('event', ['Deleted'])
        ->assertCanSeeTableRecords([$deleted])
        ->assertCanNotSeeTableRecords([$created]);
});

it('filters by subject type', function () {
    $post = createActivity(['subject_type' => 'App\\Models\\Post', 'subject_id' => 1]);
    $comment = createActivity(['subject_type' => 'App\\Models\\Comment', 'subject_id' => 1]);

    Livewire::test(ListActivities::class)
        ->filterTable('subject_type', ['App\\Models\\Post'])
        ->assertCanSeeTableRecords([$post])
        ->assertCanNotSeeTableRecords([$comment]);
});

it('filters by the user who caused the activity', function () {
    $other = User::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);

    $byAlice = createActivity(['causer_type' => User::class, 'causer_id' => $this->user->getKey()]);
    $byBob = createActivity(['causer_type' => User::class, 'causer_id' => $other->getKey()]);

    Livewire::test(ListActivities::class)
        ->filterTable('causer', ['causer_id' => $this->user->getKey()])
        ->assertCanSeeTableRecords([$byAlice])
        ->assertCanNotSeeTableRecords([$byBob]);
});

it('filters by a date preset', function () {
    $recent = createActivity(['created_at' => now()->subDay()]);
    $old = createActivity(['created_at' => now()->subDays(10)]);

    Livewire::test(ListActivities::class)
        ->filterTable('created_at', ['preset' => 'last_7_days'])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('filters by an explicit date range', function () {
    $recent = createActivity(['created_at' => now()->subDay()]);
    $old = createActivity(['created_at' => now()->subDays(10)]);

    Livewire::test(ListActivities::class)
        ->filterTable('created_at', [
            'from' => now()->subDays(12)->toDateString(),
            'until' => now()->subDays(8)->toDateString(),
        ])
        ->assertCanSeeTableRecords([$old])
        ->assertCanNotSeeTableRecords([$recent]);
});

it('keeps the old and new property filters working', function () {
    $match = createActivity(['properties' => ['old' => ['status' => 'draft'], 'attributes' => ['status' => 'published']]]);
    $other = createActivity(['properties' => ['old' => ['status' => 'archived'], 'attributes' => ['status' => 'draft']]]);

    Livewire::test(ListActivities::class)
        ->filterTable('properties->old', ['old' => 'draft'])
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

it('does not break when a property filter has no state', function () {
    Livewire::test(ListActivities::class)
        ->set('tableFilters.properties->old', [])
        ->set('tableFilters.properties->attributes', [])
        ->assertHasNoErrors();
});

describe('filter options', function () {
    it('offers the configured log names and any other log name found in the table', function () {
        createActivity(['log_name' => 'Billing']);

        expect(resourceOptions('getLogNameList'))->toMatchArray([
            'Resource' => 'Resource',
            'Model' => 'Model',
            'Access' => 'Access',
            'Notification' => 'Notification',
            'Billing' => 'Billing',
        ]);
    });

    it('leaves disabled log types out of the configured names', function () {
        config()->set('filament-logger.notifications.enabled', false);

        expect(resourceOptions('getLogNameList'))->not->toHaveKey('Notification');
    });

    it('offers the distinct events found in the table', function () {
        createActivity(['event' => 'Created']);
        createActivity(['event' => 'Created']);
        createActivity(['event' => null]);

        expect(resourceOptions('getEventList'))->toBe(['Created' => 'Created']);
    });

    it('offers registered models and stored subject types', function () {
        config()->set('filament-logger.models.register', [User::class]);
        createActivity(['subject_type' => 'App\\Models\\Post', 'subject_id' => 1]);

        expect(resourceOptions('getSubjectTypeList'))->toMatchArray([
            User::class => 'User',
            'App\\Models\\Post' => 'Post',
        ]);
    });

    it('caches the stored values briefly', function () {
        config()->set('filament-logger.filters.options_cache_ttl', 60);

        createActivity(['log_name' => 'First']);
        expect(resourceOptions('getLogNameList'))->toHaveKey('First');

        createActivity(['log_name' => 'Second']);
        expect(resourceOptions('getLogNameList'))->not->toHaveKey('Second');

        Cache::flush();
        expect(resourceOptions('getLogNameList'))->toHaveKey('Second');
    });
});
