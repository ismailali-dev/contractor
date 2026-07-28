<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Hidden::make('guard_name')
                ->default('web')
                ->dehydrated(),
            Select::make('permissions')
                ->label('Permissions')
                ->multiple()
                ->preload()
                ->searchable()
                ->relationship(
                    name: 'permissions',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $query
                        ->where('guard_name', 'web')
                        ->orderBy('name')
                )
                ->options(fn () => Permission::query()
                    ->where('guard_name', 'web')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->helperText('Optional. You can keep using roles only, or add permission checks later.'),
        ]);
    }
}

