<?php

namespace Abdiwaahid\FilamentLogger\Resources\ActivityResource\Pages;

use Abdiwaahid\FilamentLogger\Resources\ActivityResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListActivities extends ListRecords
{
    use ExposesTableToWidgets;

    protected static bool $pluginWidgetsEnabled = false;

    public static function setPluginWidgetsEnabled(bool $enabled): void
    {
        static::$pluginWidgetsEnabled = $enabled;
    }

    protected function getHeaderWidgets(): array
    {
        return static::$pluginWidgetsEnabled ? config('filament-logger.widgets', []) : [];
    }

    public static function getResource(): string
    {
        return config('filament-logger.activity_resource');
    }

    /**
     * One tab per enabled log type (plus "All"), driven by the same config as the loggers.
     * Disable with `filament-logger.tabs.enabled`.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        if (! config('filament-logger.tabs.enabled', true)) {
            return [];
        }

        $resource = static::getResource();

        $logNames = method_exists($resource, 'getConfiguredLogNames')
            ? $resource::getConfiguredLogNames()
            : ActivityResource::getConfiguredLogNames();

        $tabs = [
            'all' => Tab::make(__('filament-logger::filament-logger.tabs.all')),
        ];

        foreach ($logNames as $logName) {
            $tabs[$logName] = Tab::make($logName)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('log_name', $logName));
        }

        return $tabs;
    }
}
