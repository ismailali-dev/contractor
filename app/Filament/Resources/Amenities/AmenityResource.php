<?php

namespace App\Filament\Resources\Amenities;

use App\Filament\Resources\Amenities\Pages\CreateAmenity;
use App\Filament\Resources\Amenities\Pages\EditAmenity;
use App\Filament\Resources\Amenities\Pages\ListAmenities;
use App\Filament\Resources\Taxonomy\Schemas\TaxonomyTermForm;
use App\Filament\Resources\Taxonomy\Tables\TaxonomyTermsTable;
use App\Models\TaxonomyTerm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AmenityResource extends Resource
{
    protected static ?string $model = TaxonomyTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomy';

    protected static ?string $modelLabel = 'Amenity';

    protected static ?string $pluralModelLabel = 'Amenities';

    protected static ?string $navigationLabel = 'Amenities';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', TaxonomyTerm::TYPE_AMENITY);
    }

    public static function form(Schema $schema): Schema
    {
        return TaxonomyTermForm::configure($schema, TaxonomyTerm::TYPE_AMENITY);
    }

    public static function table(Table $table): Table
    {
        return TaxonomyTermsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAmenities::route('/'),
            'create' => CreateAmenity::route('/create'),
            'edit' => EditAmenity::route('/{record}/edit'),
        ];
    }
}
