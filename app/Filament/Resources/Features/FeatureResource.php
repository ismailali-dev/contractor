<?php

namespace App\Filament\Resources\Features;

use App\Filament\Resources\Features\Pages\CreateFeature;
use App\Filament\Resources\Features\Pages\EditFeature;
use App\Filament\Resources\Features\Pages\ListFeatures;
use App\Filament\Resources\Taxonomy\Schemas\TaxonomyTermForm;
use App\Filament\Resources\Taxonomy\Tables\TaxonomyTermsTable;
use App\Models\TaxonomyTerm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FeatureResource extends Resource
{
    protected static ?string $model = TaxonomyTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomy';

    protected static ?string $modelLabel = 'Feature';

    protected static ?string $pluralModelLabel = 'Features';

    protected static ?string $navigationLabel = 'Features';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TaxonomyTerm::TYPE_FEATURE);
    }

    public static function form(Schema $schema): Schema
    {
        return TaxonomyTermForm::configure($schema, TaxonomyTerm::TYPE_FEATURE);
    }

    public static function table(Table $table): Table
    {
        return TaxonomyTermsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatures::route('/'),
            'create' => CreateFeature::route('/create'),
            'edit' => EditFeature::route('/{record}/edit'),
        ];
    }
}
