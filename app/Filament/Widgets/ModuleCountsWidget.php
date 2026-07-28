<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ModuleCountsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $counts = Cache::remember('admin:module-counts', now()->addSeconds(30), function () {
            return Listing::query()
                ->selectRaw('module, COUNT(*) as aggregate')
                ->groupBy('module')
                ->pluck('aggregate', 'module');
        });

        return [
            Stat::make('Contractors', (string) ($counts['contractors'] ?? 0))
                ->color('info'),
            Stat::make('Real Estate', (string) ($counts['real-estate'] ?? 0))
                ->color('success'),
            Stat::make('Cars', (string) ($counts['cars'] ?? 0))
                ->color('warning'),
            Stat::make('Restaurants', (string) ($counts['restaurants'] ?? 0))
                ->color('danger'),
        ];
    }
}
