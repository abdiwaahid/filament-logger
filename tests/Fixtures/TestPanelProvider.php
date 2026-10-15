<?php

namespace Abdiwaahid\FilamentLogger\Tests\Fixtures;

use Abdiwaahid\FilamentLogger\Resources\ActivityResource;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([ActivityResource::class]);
    }
}
