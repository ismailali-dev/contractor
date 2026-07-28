<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->required()
                    ->email()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): string => strtolower(trim((string) $state))),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule(Password::defaults())
                    ->required(fn ($record): bool => $record === null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Leave blank to keep the current password when editing.'),
                Select::make('roles')
                    ->label('Roles')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->relationship(
                        name: 'roles',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query
                            ->where('guard_name', 'web')
                            ->orderBy('name')
                    )
                    ->helperText('Only users with the admin role can access /admin.'),
            ]);
    }
}
