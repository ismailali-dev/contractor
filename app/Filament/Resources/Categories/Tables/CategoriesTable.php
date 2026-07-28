<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Listing;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(url('/finder/assets/img/avatars/placeholder.png')),
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->copyable()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('module')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('icon')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('module')
                    ->options(Listing::MODULE_OPTIONS),
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
