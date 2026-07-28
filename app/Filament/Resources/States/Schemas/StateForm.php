<?php

namespace App\Filament\Resources\States\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('country_code')
                ->required()
                ->default('US')
                ->maxLength(2),
            TextInput::make('code')
                ->label('State Code')
                ->required()
                ->maxLength(2)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
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

