<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->relationship('user', 'email')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('type')
                ->options([
                    'card' => 'Card',
                    'paypal' => 'PayPal',
                ])
                ->required(),
            TextInput::make('provider')
                ->maxLength(50),
            TextInput::make('brand')
                ->maxLength(50),
            TextInput::make('card_holder_name')
                ->label('Card holder')
                ->maxLength(255),
            TextInput::make('card_last_four')
                ->label('Last 4')
                ->maxLength(4),
            TextInput::make('paypal_email')
                ->email()
                ->maxLength(255),
            TextInput::make('expiry_month')
                ->numeric()
                ->minValue(1)
                ->maxValue(12),
            TextInput::make('expiry_year')
                ->numeric()
                ->minValue(2000)
                ->maxValue(9999),
            Select::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ])
                ->required(),
            Toggle::make('is_primary')
                ->label('Primary method'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('user.email')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'paypal' ? 'info' : 'success')
                    ->sortable(),
                TextColumn::make('brand')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('card_last_four')
                    ->label('Last 4')
                    ->toggleable(),
                TextColumn::make('paypal_email')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'card' => 'Card',
                        'paypal' => 'PayPal',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('markPrimary')
                        ->label('Make primary')
                        ->color('success')
                        ->icon('heroicon-o-star')
                        ->visible(fn (PaymentMethod $record): bool => ! $record->is_primary)
                        ->requiresConfirmation()
                        ->action(function (PaymentMethod $record): void {
                            PaymentMethod::query()
                                ->where('user_id', $record->user_id)
                                ->where('id', '!=', $record->id)
                                ->update(['is_primary' => false]);

                            $record->update([
                                'is_primary' => true,
                                'status' => 'active',
                            ]);
                        }),
                    Action::make('toggleStatus')
                        ->label(fn (PaymentMethod $record): string => $record->status === 'active' ? 'Deactivate' : 'Activate')
                        ->color(fn (PaymentMethod $record): string => $record->status === 'active' ? 'gray' : 'success')
                        ->icon(fn (PaymentMethod $record): string => $record->status === 'active' ? 'heroicon-o-pause-circle' : 'heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(fn (PaymentMethod $record) => $record->update([
                            'status' => $record->status === 'active' ? 'inactive' : 'active',
                        ])),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
