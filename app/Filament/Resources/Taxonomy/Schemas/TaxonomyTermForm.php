<?php

namespace App\Filament\Resources\Taxonomy\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class TaxonomyTermForm
{
    public static function configure(Schema $schema, string $type): Schema
    {
        return $schema->components([
            Hidden::make('type')
                ->default($type)
                ->required(),
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
                ->maxLength(255)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('type', $type),
                ),
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

