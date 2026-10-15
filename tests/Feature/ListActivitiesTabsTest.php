<?php

use Abdiwaahid\FilamentLogger\Resources\ActivityResource\Pages\ListActivities;
use Abdiwaahid\FilamentLogger\Tests\Fixtures\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::create([
        'name' => 'Alice Admin',
        'email' => 'alice@example.test',
        'password' => 'secret',
    ]));
});

it('has an All tab and one tab per enabled log type', function () {
    $tabs = Livewire::test(ListActivities::class)->instance()->getTabs();

    expect(array_keys($tabs))->toBe(['all', 'Resource', 'Model', 'Access', 'Notification']);
});

it('leaves out the tabs of disabled log types', function () {
    config()->set('filament-logger.access.enabled', false);

    $tabs = Livewire::test(ListActivities::class)->instance()->getTabs();

    expect($tabs)->not->toHaveKey('Access');
});

it('adds a tab for custom log names', function () {
    config()->set('filament-logger.custom', [['log_name' => 'Billing', 'color' => 'primary']]);

    $tabs = Livewire::test(ListActivities::class)->instance()->getTabs();

    expect($tabs)->toHaveKey('Billing');
});

it('shows no tabs when they are disabled', function () {
    config()->set('filament-logger.tabs.enabled', false);

    expect(Livewire::test(ListActivities::class)->instance()->getTabs())->toBe([]);
});

it('only lists the activities of the active tab', function () {
    $access = createActivity(['log_name' => 'Access', 'event' => 'Login']);
    $resource = createActivity(['log_name' => 'Resource']);

    Livewire::test(ListActivities::class)
        ->assertCanSeeTableRecords([$access, $resource])
        ->set('activeTab', 'Access')
        ->assertCanSeeTableRecords([$access])
        ->assertCanNotSeeTableRecords([$resource]);
});
