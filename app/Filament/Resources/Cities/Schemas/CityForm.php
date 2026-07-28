<?php

namespace App\Filament\Resources\Cities\Schemas;

use App\Models\State;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (filled($state) && blank($get('slug'))) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Leave as-is to auto-update from name. You can also set custom slug.'),
                Select::make('state_code')
                    ->label('State')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(function (): array {
                        return State::query()
                            ->where('is_active', true)
                            ->orderBy('country_code')
                            ->orderBy('name')
                            ->get(['country_code', 'code', 'name'])
                            ->mapWithKeys(fn (State $state) => [
                                $state->code => "{$state->name} ({$state->code}) - {$state->country_code}",
                            ])
                            ->all();
                    }),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
