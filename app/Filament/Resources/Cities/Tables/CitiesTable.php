<?php

namespace App\Filament\Resources\Cities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use App\Models\State;

class CitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->sortable()
                    ->copyable()
                    ->searchable(),
                TextColumn::make('state_code')
                    ->label('State')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('state.name')
                    ->label('State Name')
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
                SelectFilter::make('state_code')
                    ->label('State')
                    ->options(fn () => State::query()
                        ->where('is_active', true)
                        ->orderBy('country_code')
                        ->orderBy('name')
                        ->get(['country_code', 'code', 'name'])
                        ->mapWithKeys(fn (State $state) => [
                            $state->code => "{$state->name} ({$state->code}) - {$state->country_code}",
                        ])
                        ->all()),
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
