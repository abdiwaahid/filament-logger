<?php

namespace Abdiwaahid\FilamentLogger\Tests;

use Abdiwaahid\FilamentLogger\FilamentLoggerServiceProvider;
use Abdiwaahid\FilamentLogger\Tests\Fixtures\TestPanelProvider;
use Abdiwaahid\FilamentLogger\Tests\Fixtures\User;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Abdiwaahid\\FilamentLogger\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Always read fresh filter options from the database in tests.
        config()->set('filament-logger.filters.options_cache_ttl', 0);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // A real request has Laravel's `errors` bag shared with the views (ShareErrorsFromSession),
        // Livewire::test() does not, so share an empty one like the middleware would.
        View::share('errors', new ViewErrorBag);
    }

    protected function getPackageProviders($app)
    {
        return [
            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            ActivitylogServiceProvider::class,
            FilamentLoggerServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('database.default', 'testing');
        config()->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations()
    {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('activity_log', function ($table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });
    }
}
