<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Use a stable machine name, e.g. listings.publish'),
            Hidden::make('guard_name')
                ->default('web')
                ->dehydrated(),
        ]);
    }
}

