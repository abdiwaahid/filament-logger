<?php

namespace Abdiwaahid\FilamentLogger;

use Abdiwaahid\FilamentLogger\Resources\ActivityResource;
use Abdiwaahid\FilamentLogger\Resources\ActivityResource\Pages\ListActivities;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentLoggerPlugin implements Plugin
{
    protected bool $hasWidgets = true;

    protected ?string $navigationGroup = null;

    protected ?string $resource = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-logger';
    }

    public function widgets(bool $condition = true): static
    {
        $this->hasWidgets = $condition;

        return $this;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function resource(string $resource): static
    {
        $this->resource = $resource;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $resource = $this->resource ?? config('filament-logger.activity_resource', ActivityResource::class);

        if ($this->navigationGroup !== null && method_exists($resource, 'setNavigationGroup')) {
            $resource::setNavigationGroup($this->navigationGroup);
        }

        if (method_exists($resource, 'getPages')) {
            ListActivities::setPluginWidgetsEnabled($this->hasWidgets);
        }

        $panel->resources([$resource]);

        $panel->widgets($this->hasWidgets ? config('filament-logger.widgets', []) : []);
    }

    public function boot(Panel $panel): void {}
}
