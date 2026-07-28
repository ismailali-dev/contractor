<?php

namespace App\Filament\Widgets;

use App\Models\ListingReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ReportsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        if (!Schema::hasTable('listing_reports')) {
            return [];
        }

        $row = Cache::remember('admin:reports-overview', now()->addSeconds(30), function () {
            return ListingReport::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_count', ['open'])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved_count', ['resolved'])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dismissed_count', ['dismissed'])
                ->first();
        });

        $total = (int) ($row?->total ?? 0);
        $open = (int) ($row?->open_count ?? 0);
        $resolved = (int) ($row?->resolved_count ?? 0);
        $dismissed = (int) ($row?->dismissed_count ?? 0);

        return [
            Stat::make('Reports', number_format($total))
                ->description('All time')
                ->color('primary'),
            Stat::make('Open', number_format($open))
                ->description('Needs review')
                ->color('warning'),
            Stat::make('Resolved', number_format($resolved))
                ->description('Handled')
                ->color('success'),
            Stat::make('Dismissed', number_format($dismissed))
                ->description('No action')
                ->color('gray'),
        ];
    }
}

