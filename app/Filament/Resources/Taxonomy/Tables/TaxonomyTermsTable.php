<?php

namespace App\Filament\Resources\Taxonomy\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxonomyTermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('sort_order')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->defaultSort('sort_order');
    }
}
