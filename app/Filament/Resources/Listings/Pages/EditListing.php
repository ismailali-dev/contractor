<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Listings\Pages\Concerns\HandlesListingDetails;
use App\Models\ListingClaimRequest;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditListing extends EditRecord
{
    use HandlesListingDetails;

    protected static string $resource = ListingResource::class;

    protected array $listingFormData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->mutateListingDetailFormDataBeforeFill($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = $this->normalizeAdminListingFeaturesBeforeSave($data);
        $data = $this->normalizePriceForListing($data);
        $data = $this->mergeRestaurantMetaIntoExcerpt($data);

        $status = (string) ($data['status'] ?? 'draft');

        if ($status === 'published' && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        if ($status === 'draft') {
            $data['published_at'] = null;
        }

        $this->listingFormData = $data;
        $this->assertPublishRequirements($data, false);

        return $this->stripExtraFormData($data);
    }

    protected function afterSave(): void
    {
        $this->syncListingRelations($this->listingFormData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('removeClaim')
                ->label('Remove Claim')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->visible(function (): bool {
                    return ListingClaimRequest::query()
                        ->where('listing_id', $this->record->id)
                        ->whereNotNull('used_at')
                        ->exists();
                })
                ->requiresConfirmation()
                ->modalHeading('Remove listing claim?')
                ->modalDescription('This will unclaim the listing, remove its claimed state from the public detail page, and let it be claimed again later.')
                ->action(function (): void {
                    DB::transaction(function (): void {
                        $this->record->forceFill([
                            'user_id' => null,
                        ])->save();

                        ListingClaimRequest::query()
                            ->where('listing_id', $this->record->id)
                            ->delete();
                    });

                    Notification::make()
                        ->title('Listing claim removed.')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                }),
            DeleteAction::make(),
        ];
    }
}
