<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Ops extends Page
{
    protected static ?string $navigationLabel = 'Ops';

    protected static \UnitEnum|string|null $navigationGroup = 'Ops';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected string $view = 'filament.pages.ops';

    public static function shouldRegisterNavigation(): bool
    {
        // Always show; actions will fail gracefully if commands are unavailable.
        return parent::shouldRegisterNavigation();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('optimizeClear')
                ->label('Clear caches')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        Artisan::call('optimize:clear');
                    } catch (\Throwable $e) {
                        // Ignore; UI will still render.
                    }
                    Cache::flush();
                }),
            Action::make('clearStatsCache')
                ->label('Clear stats cache')
                ->icon('heroicon-o-chart-bar')
                ->requiresConfirmation()
                ->action(function (): void {
                    Cache::forget('admin:listings-overview');
                    Cache::forget('admin:module-counts');
                    Cache::forget('admin:reports-overview');
                }),
            Action::make('exportListings')
                ->label('Export listings CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => url('/admin/exports/listings.csv'))
                ->openUrlInNewTab(),
            Action::make('exportReports')
                ->label('Export reports CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => Schema::hasTable('listing_reports'))
                ->url(fn (): string => url('/admin/exports/reports.csv'))
                ->openUrlInNewTab(),
        ];
    }
}
