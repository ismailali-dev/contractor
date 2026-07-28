<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Models\Listing;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        $hasAdminStatus = false;
        try {
            $hasAdminStatus = Schema::hasColumn('listings', 'admin_status');
        } catch (\Throwable $e) {
            $hasAdminStatus = false;
        }

        $columns = [
            TextColumn::make('user.name')
                ->searchable(),
            TextColumn::make('category.name')
                ->searchable(),
            TextColumn::make('city.name')
                ->searchable(),
            TextColumn::make('module')
                ->badge()
                ->sortable()
                ->searchable(),
            TextColumn::make('title')
                ->sortable()
                ->searchable(),
            TextColumn::make('slug')
                ->copyable()
                ->searchable(),
            TextColumn::make('price')
                ->searchable(),
            TextColumn::make('rating')
                ->numeric()
                ->sortable(),
            TextColumn::make('reviews_count')
                ->numeric()
                ->sortable(),
            ImageColumn::make('image')
                ->disk('public'),
            TextColumn::make('status')
                ->badge(),
        ];

        if ($hasAdminStatus) {
            $columns[] = TextColumn::make('admin_status')
                ->label('Review')
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'approved' => 'success',
                    'pending' => 'warning',
                    'rejected' => 'danger',
                    default => 'gray',
                })
                ->toggleable();
        }

        $columns[] = TextColumn::make('published_at')
            ->dateTime()
            ->sortable();
        $columns[] = TextColumn::make('created_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
        $columns[] = TextColumn::make('updated_at')
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        $filters = [
            SelectFilter::make('module')
                ->options(Listing::MODULE_OPTIONS),
            SelectFilter::make('status')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ]),
        ];
        if ($hasAdminStatus) {
            $filters[] = SelectFilter::make('admin_status')
                ->label('Review Status')
                ->options([
                    'approved' => 'Approved',
                    'pending' => 'Pending',
                    'rejected' => 'Rejected',
                ]);
        }

        $recordActions = [
            Action::make('viewOnSite')
                ->label('View')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(function (Listing $record): string {
                    $isPublished = strtolower(trim((string) $record->status)) === 'published';
                    return url('/app/entry/' . $record->slug . ($isPublished ? '' : '?preview=1'));
                })
                ->openUrlInNewTab(),
            Action::make('note')
                ->label('Add note')
                ->icon('heroicon-o-pencil-square')
                ->form([
                    Textarea::make('message')
                        ->label('Moderation note')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (Listing $record, array $data): void {
                    $message = trim((string) ($data['message'] ?? ''));
                    if ($message === '') {
                        return;
                    }
                    $record->logModeration('note', $message);
                }),
        ];

        if ($hasAdminStatus) {
            $recordActions[] = Action::make('approvePublish')
                ->label('Approve & publish')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Listing $record): bool => $record->status !== 'published' || $record->admin_status !== 'approved')
                ->action(function (Listing $record): void {
                    $from = ['status' => $record->status, 'admin_status' => $record->admin_status];
                    $record->update([
                        'admin_status' => 'approved',
                        'rejection_reason' => null,
                        'status' => 'published',
                        'published_at' => $record->published_at ?? now(),
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                    ]);
                    $to = ['status' => $record->status, 'admin_status' => $record->admin_status];
                    $record->logModeration('approve_publish', null, ['from' => $from, 'to' => $to]);
                });

            $recordActions[] = Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->form([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (Listing $record, array $data): void {
                    $reason = trim((string) ($data['reason'] ?? ''));
                    $from = ['status' => $record->status, 'admin_status' => $record->admin_status];
                    $record->update([
                        'admin_status' => 'rejected',
                        'rejection_reason' => $reason !== '' ? $reason : null,
                        'status' => 'draft',
                        'published_at' => null,
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                    ]);
                    $to = ['status' => $record->status, 'admin_status' => $record->admin_status];
                    $record->logModeration('reject', $reason, ['from' => $from, 'to' => $to]);
                });
        }

        $recordActions[] = EditAction::make();

        return $table
            ->defaultSort('id', 'desc')
            ->columns($columns)
            ->filters($filters)
            ->recordActions($recordActions)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publishSelected')
                        ->label('Publish selected')
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $now = now();
                            foreach ($records as $record) {
                                $record->update([
                                    'status' => 'published',
                                    'published_at' => $record->published_at ?? $now,
                                ]);
                            }
                        }),
                    BulkAction::make('moveToDraft')
                        ->label('Move to draft')
                        ->icon('heroicon-o-pencil-square')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                $record->update([
                                    'status' => 'draft',
                                    'published_at' => null,
                                ]);
                            }
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
