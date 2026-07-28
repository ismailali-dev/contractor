<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Listing;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
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
                    ->maxLength(255)
                    ->helperText('Leave as-is to auto-update from name. You can also set custom slug.'),
                Select::make('module')
                    ->required()
                    ->options(Listing::MODULE_OPTIONS),
                TextInput::make('icon')
                    ->maxLength(100)
                    ->helperText('Optional icon class, e.g. fi fi-tools'),
                FileUpload::make('image')
                    ->label('Category Image')
                    ->image()
                    ->disk('public')
                    ->directory('categories')
                    ->imageEditor(),
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
