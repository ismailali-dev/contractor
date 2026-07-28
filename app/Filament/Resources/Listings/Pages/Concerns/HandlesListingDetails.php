<?php

namespace App\Filament\Resources\Listings\Pages\Concerns;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesListingDetails
{
    /**
     * Feature tokens that are editable from the Filament checkbox list.
     *
     * @return array<int, string>
     */
    protected function editableListingFeatureKeys(): array
    {
        return [
            'eco-friendly',
            'free-consultation',
            'online-consultation',
            'free-estimate',
            'verified-hires',
            'weekend-consultations',
        ];
    }

    protected function normalizeAdminListingFeaturesBeforeFill(array $data): array
    {
        $module = (string) ($data['module'] ?? '');
        if (! in_array($module, ['contractors', 'restaurants'], true)) {
            return $data;
        }

        $editableKeys = $this->editableListingFeatureKeys();
        $data['features'] = collect((array) ($data['features'] ?? []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => in_array($value, $editableKeys, true))
            ->values()
            ->all();

        return $data;
    }

    protected function normalizeAdminListingFeaturesBeforeSave(array $data): array
    {
        $module = (string) ($data['module'] ?? '');
        if (! in_array($module, ['contractors', 'restaurants'], true)) {
            $data['features'] = [];
            return $data;
        }

        $editableKeys = $this->editableListingFeatureKeys();
        $selected = collect((array) ($data['features'] ?? []))
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => in_array($value, $editableKeys, true))
            ->values()
            ->all();

        $preservedHidden = [];
        if (property_exists($this, 'record') && $this->record instanceof Listing) {
            $preservedHidden = collect((array) ($this->record->features ?? []))
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '' && ! in_array($value, $editableKeys, true))
                ->values()
                ->all();
        }

        $data['features'] = array_values(array_unique(array_merge($selected, $preservedHidden)));

        return $data;
    }

    protected function normalizePriceForListing(array $data): array
    {
        $isMonthlyRent = (($data['module'] ?? '') === 'real-estate')
            && (($data['property_listing_type'] ?? '') === 'rent');

        $data['price'] = Listing::normalizePrice($data['price'] ?? null, $isMonthlyRent);

        return $data;
    }

    protected function assertPublishRequirements(array $data, bool $isCreate = false): void
    {
        $status = (string) ($data['status'] ?? 'draft');
        if ($status !== 'published') {
            return;
        }

        $module = (string) ($data['module'] ?? '');
        $errors = [];

        if (blank($data['image'] ?? null)) {
            $errors['image'][] = 'Cover image is required before publishing.';
        }

        if (blank($data['price'] ?? null)) {
            $errors['price'][] = 'Price is required before publishing.';
        }

        $rating = is_numeric($data['rating'] ?? null) ? (float) $data['rating'] : null;
        if ($rating === null || $rating < 0 || $rating > 5) {
            $errors['rating'][] = 'Rating must be between 0 and 5.';
        }

        $reviewsCount = is_numeric($data['reviews_count'] ?? null) ? (int) $data['reviews_count'] : null;
        if ($reviewsCount === null || $reviewsCount < 0) {
            $errors['reviews_count'][] = 'Reviews count must be 0 or greater.';
        }

        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId > 0) {
            $category = Category::query()->find($categoryId);
            if (! $category || $category->module !== $module) {
                $errors['category_id'][] = 'Selected category does not match listing module.';
            }
        }

        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $coverImagePath = (string) ($data['image'] ?? '');

        if ($coverImagePath !== '') {
            $coverExt = strtolower(pathinfo(parse_url($coverImagePath, PHP_URL_PATH) ?: $coverImagePath, PATHINFO_EXTENSION));
            if ($coverExt !== '' && !in_array($coverExt, $allowedImageExtensions, true)) {
                $errors['image'][] = 'Cover image must be JPG, PNG, or WEBP.';
            }

            if (!Str::startsWith($coverImagePath, ['http://', 'https://', '/']) && !Storage::disk('public')->exists($coverImagePath)) {
                $errors['image'][] = 'Cover image file was not found in storage.';
            }
        }

        $galleryImages = array_values(array_filter((array) ($data['gallery_images'] ?? [])));
        foreach ($galleryImages as $index => $imagePath) {
            $path = (string) $imagePath;
            if ($path === '') {
                continue;
            }

            $ext = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION));
            if ($ext !== '' && !in_array($ext, $allowedImageExtensions, true)) {
                $errors["gallery_images.{$index}"][] = 'Gallery image must be JPG, PNG, or WEBP.';
            }

            if (!Str::startsWith($path, ['http://', 'https://', '/']) && !Storage::disk('public')->exists($path)) {
                $errors["gallery_images.{$index}"][] = 'Gallery image file was not found in storage.';
            }
        }

        if ($module === 'contractors' && blank($data['contractor_service_area'] ?? null)) {
            $errors['contractor_service_area'][] = 'Service area is required for contractor listings.';
        }

        if ($module === 'real-estate') {
            if (blank($data['property_bedrooms'] ?? null)) {
                $errors['property_bedrooms'][] = 'Bedrooms are required for real estate listings.';
            }
            if (blank($data['property_area_sqft'] ?? null)) {
                $errors['property_area_sqft'][] = 'Area (sqft) is required for real estate listings.';
            }
        }

        if ($module === 'cars') {
            if (blank($data['car_year'] ?? null)) {
                $errors['car_year'][] = 'Year is required for car listings.';
            }
            if (blank($data['car_mileage'] ?? null)) {
                $errors['car_mileage'][] = 'Mileage is required for car listings.';
            }
        }

        // Gallery images enforcement can be strict for new listings, but for existing listings we allow saving
        // (admin may be fixing metadata/badges) even if the listing currently has no gallery yet.
        if ($isCreate && in_array($module, ['real-estate', 'cars'], true)) {
            $galleryCount = count($galleryImages);
            if ($galleryCount < 1) {
                $errors['gallery_images'][] = 'Add at least one gallery image for published listings.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function stripExtraFormData(array $data): array
    {
        unset(
            $data['gallery_images'],
            $data['contractor_service_area'],
            $data['contractor_license_number'],
            $data['contractor_is_verified'],
            $data['contractor_business_hours'],
            $data['property_type'],
            $data['property_listing_type'],
            $data['property_bedrooms'],
            $data['property_bathrooms'],
            $data['property_area_sqft'],
            $data['car_year'],
            $data['car_mileage'],
            $data['car_fuel_type'],
            $data['car_transmission'],
            $data['car_body_type'],
            $data['car_is_verified'],
            $data['restaurant_address'],
            $data['restaurant_zip_code'],
            $data['restaurant_state'],
            $data['restaurant_seating_capacity'],
            $data['restaurant_services'],
            $data['restaurant_opening_hours_json'],
            $data['restaurant_contact_name'],
            $data['restaurant_phone'],
            $data['restaurant_email'],
            $data['event_starts_at'],
            $data['event_ends_at'],
            $data['event_venue'],
            $data['event_capacity'],
        );

        return $data;
    }

    protected function mergeRestaurantMetaIntoExcerpt(array $data): array
    {
        if (($data['module'] ?? '') !== 'restaurants') {
            return $data;
        }

        $openingHours = [];
        $openingHoursRaw = trim((string) ($data['restaurant_opening_hours_json'] ?? ''));
        if ($openingHoursRaw !== '') {
            $decoded = json_decode($openingHoursRaw, true);
            if (is_array($decoded)) {
                $openingHours = $decoded;
            }
        }

        $services = (array) ($data['restaurant_services'] ?? []);
        $services = array_values(array_unique(array_values(array_filter(array_map(fn ($v) => trim((string) $v), $services)))));

        $meta = [
            '_mc_restaurant_v1' => true,
            'address' => trim((string) ($data['restaurant_address'] ?? '')),
            'zip_code' => trim((string) ($data['restaurant_zip_code'] ?? '')),
            'country' => 'United States',
            'state' => trim((string) ($data['restaurant_state'] ?? '')),
            'seating_capacity' => trim((string) ($data['restaurant_seating_capacity'] ?? '')),
            'services' => $services,
            'opening_hours' => $openingHours,
            'contact_name' => trim((string) ($data['restaurant_contact_name'] ?? '')),
            'phone' => trim((string) ($data['restaurant_phone'] ?? '')),
            'email' => trim((string) ($data['restaurant_email'] ?? '')),
        ];

        $data['excerpt'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $data;
    }

    protected function mutateListingDetailFormDataBeforeFill(array $data): array
    {
        /** @var Listing $record */
        $record = $this->record;

        $data = $this->normalizeAdminListingFeaturesBeforeFill($data);

        $data['gallery_images'] = $record->images()->orderBy('sort_order')->pluck('image_path')->all();

        $contractor = $record->contractorDetail;
        $data['contractor_service_area'] = $contractor?->service_area;
        $data['contractor_license_number'] = $contractor?->license_number;
        $data['contractor_is_verified'] = (bool) ($contractor?->is_verified);
        $data['contractor_business_hours'] = $contractor?->business_hours ?? [];

        $property = $record->propertyDetail;
        $data['property_type'] = $property?->property_type;
        $data['property_listing_type'] = $property?->listing_type;
        $data['property_bedrooms'] = $property?->bedrooms;
        $data['property_bathrooms'] = $property?->bathrooms;
        $data['property_area_sqft'] = $property?->area_sqft;

        $car = $record->carDetail;
        $data['car_year'] = $car?->year;
        $data['car_mileage'] = $car?->mileage;
        $data['car_fuel_type'] = $car?->fuel_type;
        $data['car_transmission'] = $car?->transmission;
        $data['car_body_type'] = $car?->body_type;
        $data['car_is_verified'] = (bool) ($car?->is_verified ?? false);

        if (($record->module ?? '') === 'restaurants') {
            $excerpt = trim((string) ($record->excerpt ?? ''));
            if ($excerpt !== '' && (str_starts_with($excerpt, '{') || str_starts_with($excerpt, '['))) {
                $decoded = json_decode($excerpt, true);
                if (is_array($decoded) && ($decoded['_mc_restaurant_v1'] ?? false)) {
                    $data['restaurant_address'] = $decoded['address'] ?? null;
                    $data['restaurant_zip_code'] = $decoded['zip_code'] ?? null;
                    $data['restaurant_state'] = $decoded['state'] ?? ($record->city?->state_code ?? null);
                    $data['restaurant_seating_capacity'] = $decoded['seating_capacity'] ?? null;
                    $data['restaurant_services'] = is_array($decoded['services'] ?? null) ? $decoded['services'] : [];
                    $data['restaurant_opening_hours_json'] = json_encode($decoded['opening_hours'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $data['restaurant_contact_name'] = $decoded['contact_name'] ?? null;
                    $data['restaurant_phone'] = $decoded['phone'] ?? null;
                    $data['restaurant_email'] = $decoded['email'] ?? null;
                }
            }
        }

        $event = $record->eventDetail;
        $data['event_starts_at'] = $event?->starts_at;
        $data['event_ends_at'] = $event?->ends_at;
        $data['event_venue'] = $event?->venue;
        $data['event_capacity'] = $event?->capacity;

        return $data;
    }

    protected function syncListingRelations(array $data): void
    {
        /** @var Listing $listing */
        $listing = $this->record;

        $module = (string) ($listing->module ?? '');

        if ($module === 'contractors') {
            $listing->contractorDetail()->updateOrCreate(
                ['listing_id' => $listing->id],
                [
                    'service_area' => $data['contractor_service_area'] ?? null,
                    'license_number' => $data['contractor_license_number'] ?? null,
                    'is_verified' => (bool) ($data['contractor_is_verified'] ?? false),
                    'business_hours' => $data['contractor_business_hours'] ?? null,
                ]
            );
        } else {
            $listing->contractorDetail()->delete();
        }

        if ($module === 'real-estate') {
            $listing->propertyDetail()->updateOrCreate(
                ['listing_id' => $listing->id],
                [
                    'property_type' => $data['property_type'] ?? null,
                    'listing_type' => $data['property_listing_type'] ?? null,
                    'bedrooms' => $data['property_bedrooms'] ?? null,
                    'bathrooms' => $data['property_bathrooms'] ?? null,
                    'area_sqft' => $data['property_area_sqft'] ?? null,
                ]
            );
        } else {
            $listing->propertyDetail()->delete();
        }

        if ($module === 'cars') {
            $listing->carDetail()->updateOrCreate(
                ['listing_id' => $listing->id],
                [
                    'year' => $data['car_year'] ?? null,
                    'mileage' => $data['car_mileage'] ?? null,
                    'fuel_type' => $data['car_fuel_type'] ?? null,
                    'transmission' => $data['car_transmission'] ?? null,
                    'body_type' => $data['car_body_type'] ?? null,
                    'is_verified' => (bool) ($data['car_is_verified'] ?? false),
                ]
            );
        } else {
            $listing->carDetail()->delete();
        }

        if ($module === 'restaurants') {
            $data = $this->mergeRestaurantMetaIntoExcerpt($data);
            $listing->excerpt = $data['excerpt'] ?? $listing->excerpt;
            $listing->save();
        }

        $listing->eventDetail()->delete();

        $galleryImages = array_values(array_filter((array) ($data['gallery_images'] ?? [])));

        $listing->images()->delete();

        foreach ($galleryImages as $index => $path) {
            $listing->images()->create([
                'image_path' => $path,
                'sort_order' => $index + 1,
                'is_cover' => false,
            ]);
        }
    }
}
