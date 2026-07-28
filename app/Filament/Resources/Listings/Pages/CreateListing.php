<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Listings\Pages\Concerns\HandlesListingDetails;
use Filament\Resources\Pages\CreateRecord;

class CreateListing extends CreateRecord
{
    use HandlesListingDetails;

    protected static string $resource = ListingResource::class;

    protected static bool $canCreateAnother = false;

    protected array $listingFormData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
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
        $this->assertPublishRequirements($data, true);

        return $this->stripExtraFormData($data);
    }

    protected function afterCreate(): void
    {
        $this->syncListingRelations($this->listingFormData);
    }
}
