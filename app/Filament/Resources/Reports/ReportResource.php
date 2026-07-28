<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\CreateReport;
use App\Filament\Resources\Reports\Pages\EditReport;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\Schemas\ReportForm;
use App\Filament\Resources\Reports\Tables\ReportsTable;
use App\Models\ListingReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class ReportResource extends Resource
{
    protected static ?string $model = ListingReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static \UnitEnum|string|null $navigationGroup = 'Moderation';

    protected static ?string $modelLabel = 'Report';

    protected static ?string $pluralModelLabel = 'Reports';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 50;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            if (!DatabaseSchema::hasTable('listing_reports')) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return ReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
            'create' => CreateReport::route('/create'),
            'edit' => EditReport::route('/{record}/edit'),
        ];
    }
}
