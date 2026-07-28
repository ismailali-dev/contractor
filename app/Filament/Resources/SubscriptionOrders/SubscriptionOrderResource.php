<?php

namespace App\Filament\Resources\SubscriptionOrders;

use App\Filament\Resources\SubscriptionOrders\Pages\EditSubscriptionOrder;
use App\Filament\Resources\SubscriptionOrders\Pages\ListSubscriptionOrders;
use App\Models\SubscriptionOrder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionOrderResource extends Resource
{
    protected static ?string $model = SubscriptionOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('order_number')
                ->disabled()
                ->dehydrated(false),
            Select::make('user_id')
                ->relationship('user', 'email')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('listing_id')
                ->relationship('listing', 'title')
                ->searchable()
                ->preload(),
            Select::make('payment_method_id')
                ->relationship('paymentMethod', 'id')
                ->searchable()
                ->preload(),
            TextInput::make('module')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('package_label')
                ->maxLength(255),
            TextInput::make('package_price')
                ->maxLength(255),
            Select::make('status')
                ->options([
                    'active' => 'Active',
                    'previous' => 'Previous',
                    'cancelled' => 'Cancelled',
                ])
                ->required(),
            Select::make('admin_status')
                ->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'paused' => 'Paused',
                    'cancelled' => 'Cancelled',
                ])
                ->required(),
            DateTimePicker::make('started_at'),
            DateTimePicker::make('ends_at'),
            Textarea::make('notes')
                ->rows(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('listing.title')
                    ->label('Listing')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('module')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => \App\Models\Listing::MODULE_OPTIONS[(string) $state] ?? ucfirst(str_replace('-', ' ', (string) $state))),
                TextColumn::make('package_label')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('package_price')
                    ->label('Price')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'previous' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('admin_status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'paused' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('paymentMethod.type')
                    ->label('Payment')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'previous' => 'Previous',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('admin_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'paused' => 'Paused',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('module')
                    ->options(\App\Models\Listing::MODULE_OPTIONS),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('approve')
                        ->label('Approve')
                        ->color('success')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn (SubscriptionOrder $record): bool => $record->admin_status !== 'approved')
                        ->requiresConfirmation()
                        ->action(fn (SubscriptionOrder $record) => $record->update([
                            'admin_status' => 'approved',
                            'status' => 'active',
                        ])),
                    Action::make('pause')
                        ->label('Pause')
                        ->color('gray')
                        ->icon('heroicon-o-pause-circle')
                        ->visible(fn (SubscriptionOrder $record): bool => $record->admin_status !== 'paused')
                        ->requiresConfirmation()
                        ->action(fn (SubscriptionOrder $record) => $record->update([
                            'admin_status' => 'paused',
                        ])),
                    Action::make('cancel')
                        ->label('Cancel')
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->visible(fn (SubscriptionOrder $record): bool => $record->admin_status !== 'cancelled')
                        ->requiresConfirmation()
                        ->action(fn (SubscriptionOrder $record) => $record->update([
                            'admin_status' => 'cancelled',
                            'status' => 'cancelled',
                        ])),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionOrders::route('/'),
            'edit' => EditSubscriptionOrder::route('/{record}/edit'),
        ];
    }
}
