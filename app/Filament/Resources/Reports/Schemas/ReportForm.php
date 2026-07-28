<?php

namespace App\Filament\Resources\Reports\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('listing_id')
                ->relationship('listing', 'title')
                ->searchable()
                ->required(),
            Select::make('reason')
                ->options([
                    'spam' => 'Spam',
                    'scam' => 'Scam / Fraud',
                    'duplicate' => 'Duplicate',
                    'inappropriate' => 'Inappropriate',
                    'other' => 'Other',
                ])
                ->required(),
            Textarea::make('message')
                ->rows(5)
                ->columnSpanFull(),
            Select::make('status')
                ->options([
                    'open' => 'Open',
                    'resolved' => 'Resolved',
                    'dismissed' => 'Dismissed',
                ])
                ->default('open')
                ->required(),
            Textarea::make('admin_note')
                ->label('Moderation note')
                ->helperText('Internal note, not visible to users.')
                ->rows(4)
                ->columnSpanFull(),
            TextInput::make('reporter_email')
                ->label('Reporter email')
                ->email()
                ->visible(fn (Get $get): bool => (string) ($get('reporter_user_id') ?? '') === ''),
        ]);
    }
}

