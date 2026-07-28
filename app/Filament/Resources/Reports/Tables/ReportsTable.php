<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Models\ListingReport;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('listing.title')
                    ->label('Listing')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('listing.module')
                    ->label('Module')
                    ->badge()
                    ->sortable(),
                TextColumn::make('reason')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'spam' => 'warning',
                        'scam' => 'danger',
                        'duplicate' => 'gray',
                        'inappropriate' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'warning',
                        'resolved' => 'success',
                        'dismissed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('reporter.name')
                    ->label('Reporter')
                    ->toggleable(),
                TextColumn::make('reporter_email')
                    ->label('Reporter email')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'resolved' => 'Resolved',
                        'dismissed' => 'Dismissed',
                    ])
                    ->default('open'),
                SelectFilter::make('reason')
                    ->options([
                        'spam' => 'Spam',
                        'scam' => 'Scam / Fraud',
                        'duplicate' => 'Duplicate',
                        'inappropriate' => 'Inappropriate',
                        'other' => 'Other',
                    ]),
            ])
            ->recordActions([
                Action::make('viewOnSite')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ListingReport $record): string => url('/app/entry/' . ($record->listing?->slug ?? '')))
                    ->openUrlInNewTab()
                    ->visible(fn (ListingReport $record): bool => (bool) $record->listing?->slug),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ListingReport $record): bool => $record->status === 'open')
                    ->action(function (ListingReport $record): void {
                        $record->status = 'resolved';
                        $record->resolved_at = now();
                        $record->resolved_by = auth()->id();
                        $record->save();
                    }),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (ListingReport $record): bool => $record->status === 'open')
                    ->action(function (ListingReport $record): void {
                        $record->status = 'dismissed';
                        $record->resolved_at = now();
                        $record->resolved_by = auth()->id();
                        $record->save();
                    }),
                EditAction::make(),
            ]);
    }
}

