<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Filament\Resources\Taxonomy\Schemas\TaxonomyTermForm;
use App\Filament\Resources\Taxonomy\Tables\TaxonomyTermsTable;
use App\Models\TaxonomyTerm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceResource extends Resource
{
    protected static ?string $model = TaxonomyTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomy';

    protected static ?string $modelLabel = 'Service';

    protected static ?string $pluralModelLabel = 'Services';

    protected static ?string $navigationLabel = 'Services';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TaxonomyTerm::TYPE_SERVICE);
    }

    public static function form(Schema $schema): Schema
    {
        return TaxonomyTermForm::configure($schema, TaxonomyTerm::TYPE_SERVICE);
    }

    public static function table(Table $table): Table
    {
        return TaxonomyTermsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
