<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListingsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $row = Cache::remember('admin:listings-overview', now()->addSeconds(30), function () {
            return Listing::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as published', ['published'])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft', ['draft'])
                ->first();
        });

        $total = (int) ($row?->total ?? 0);
        $published = (int) ($row?->published ?? 0);
        $draft = (int) ($row?->draft ?? 0);

        return [
            Stat::make('Total Listings', number_format($total))
                ->description('All modules')
                ->color('primary'),
            Stat::make('Published', number_format($published))
                ->description('Live on frontend')
                ->color('success'),
            Stat::make('Draft', number_format($draft))
                ->description('Pending publish')
                ->color('warning'),
        ];
    }
}
