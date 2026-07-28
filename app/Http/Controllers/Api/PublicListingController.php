<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use App\Models\ListingClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicListingController extends Controller
{
    private function resolvePreferredContractorAddress(?string $storedAddress, ?string $fallbackAddress, ?string $cityName): ?string
    {
        $stored = trim((string) $storedAddress);
        $fallback = trim((string) $fallbackAddress);
        $city = trim((string) $cityName);

        if ($stored === '' && $fallback === '') {
            return null;
        }

        if ($stored === '') {
            return $fallback !== '' ? $fallback : null;
        }

        if ($fallback === '') {
            return $stored;
        }

        $storedLower = strtolower($stored);
        $fallbackLower = strtolower($fallback);
        $cityLower = strtolower($city);

        $storedLooksLikeCityOnly = $cityLower !== '' && $storedLower === $cityLower;
        $fallbackContainsStored = $stored !== '' && str_contains($fallbackLower, $storedLower);

        if ($storedLooksLikeCityOnly || (strlen($fallback) > strlen($stored) && $fallbackContainsStored)) {
            return $fallback;
        }

        return $stored;
    }

    private function resolvePreferredContractorValue(?string $primary, ?string $fallback): ?string
    {
        $primaryValue = trim((string) $primary);
        if ($primaryValue !== '') {
            return $primaryValue;
        }

        $fallbackValue = trim((string) $fallback);
        return $fallbackValue !== '' ? $fallbackValue : null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function normalizeCarTitle(?string $title): string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $title));
        if ($normalized === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+\(?\d{4}\)?$/', '', $normalized));
    }

    /**
     * @return array{make: string, model: string}
     */
    private function inferCarIdentity(?string $brand, ?string $model, ?string $title): array
    {
        $resolvedMake = trim((string) $brand);
        $resolvedModel = trim((string) $model);
        $normalizedTitle = $this->normalizeCarTitle($title);

        if ($normalizedTitle !== '' && ($resolvedMake === '' || $resolvedModel === '')) {
            $parts = preg_split('/\s+/', $normalizedTitle, 2) ?: [];
            if ($resolvedMake === '') {
                $resolvedMake = trim((string) ($parts[0] ?? ''));
            }
            if ($resolvedModel === '') {
                $resolvedModel = trim((string) ($parts[1] ?? ''));
            }
        }

        return [
            'make' => $resolvedMake,
            'model' => $resolvedModel,
        ];
    }

    private function applyCarsMakeFilter($query, string $make): void
    {
        $normalizedMake = strtolower(trim($make));
        if ($normalizedMake === '') {
            return;
        }

        $titlePrefix = $this->escapeLike($normalizedMake) . ' %';

        $query->where(function ($builder) use ($normalizedMake, $titlePrefix): void {
            if (Schema::hasTable('car_details') && Schema::hasColumn('car_details', 'brand')) {
                $builder->whereHas('carDetail', function ($detailBuilder) use ($normalizedMake): void {
                    $detailBuilder->whereRaw('LOWER(TRIM(COALESCE(brand, ""))) = ?', [$normalizedMake]);
                });
                $builder->orWhereRaw('LOWER(TRIM(title)) = ?', [$normalizedMake])
                    ->orWhereRaw("LOWER(TRIM(title)) LIKE ? ESCAPE '\\\\'", [$titlePrefix]);
                return;
            }

            $builder->whereRaw('LOWER(TRIM(title)) = ?', [$normalizedMake])
                ->orWhereRaw("LOWER(TRIM(title)) LIKE ? ESCAPE '\\\\'", [$titlePrefix]);
        });
    }

    private function applyCarsModelFilter($query, string $model, string $make = ''): void
    {
        $normalizedModel = strtolower(trim($model));
        if ($normalizedModel === '') {
            return;
        }

        $normalizedMake = strtolower(trim($make));
        $combinedPrefix = trim($normalizedMake . ' ' . $normalizedModel);
        $titlePattern = $combinedPrefix !== ''
            ? $this->escapeLike($combinedPrefix) . '%'
            : '%' . $this->escapeLike($normalizedModel) . '%';

        $query->where(function ($builder) use ($normalizedModel, $titlePattern): void {
            if (Schema::hasTable('car_details') && Schema::hasColumn('car_details', 'model')) {
                $builder->whereHas('carDetail', function ($detailBuilder) use ($normalizedModel): void {
                    $detailBuilder->whereRaw('LOWER(TRIM(COALESCE(model, ""))) = ?', [$normalizedModel]);
                });
                $builder->orWhereRaw("LOWER(TRIM(title)) LIKE ? ESCAPE '\\\\'", [$titlePattern]);
                return;
            }

            $builder->whereRaw("LOWER(TRIM(title)) LIKE ? ESCAPE '\\\\'", [$titlePattern]);
        });
    }

    /**
     * @return array<int, string>
     */
    private function normalizedCsvValues(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn (string $value) => strtolower(trim($value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function carFuelTokens(?string $fuelType): array
    {
        $normalized = strtolower(trim((string) $fuelType));
        if ($normalized === '') {
            return [];
        }

        $tokens = [];
        if (str_contains($normalized, 'diesel')) {
            $tokens[] = 'diesel';
        }
        if (str_contains($normalized, 'electric')) {
            $tokens[] = 'electric';
        }
        if (str_contains($normalized, 'hybrid')) {
            $tokens[] = str_contains($normalized, 'plug') ? 'plugin' : 'hybrid';
        }
        if (str_contains($normalized, 'hydrogen')) {
            $tokens[] = 'hydrogen';
        }
        if (str_contains($normalized, 'gasoline') || str_contains($normalized, 'petrol') || str_contains($normalized, 'gas ')) {
            $tokens[] = 'gas';
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array<int, string>
     */
    private function carTransmissionTokens(?string $transmission): array
    {
        $normalized = strtolower(trim((string) $transmission));
        if ($normalized === '') {
            return [];
        }

        $tokens = [];
        if (str_contains($normalized, 'automatic')) {
            $tokens[] = 'auto';
        }
        if (str_contains($normalized, 'manual')) {
            $tokens[] = 'manual';
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array<int, string>
     */
    private function carDriveTypeTokens(?string $driveType): array
    {
        $normalized = strtolower(trim((string) $driveType));
        if ($normalized === '') {
            return [];
        }

        $tokens = [];
        if (str_contains($normalized, 'awd') || str_contains($normalized, '4wd') || str_contains($normalized, 'all wheel')) {
            $tokens[] = 'awd';
        }
        if (str_contains($normalized, 'fwd') || str_contains($normalized, 'front wheel')) {
            $tokens[] = 'fwd';
        }
        if (str_contains($normalized, 'rwd') || str_contains($normalized, 'rear wheel')) {
            $tokens[] = 'rwd';
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array<int, string>
     */
    private function carBodyTypeTokens(?string $bodyType): array
    {
        $normalized = strtolower(trim((string) $bodyType));
        if ($normalized === '') {
            return [];
        }

        $tokens = [];
        if (str_contains($normalized, 'sedan')) {
            $tokens[] = 'sedan';
        }
        if (str_contains($normalized, 'suv')) {
            $tokens[] = 'suv';
        }
        if (str_contains($normalized, 'wagon')) {
            $tokens[] = 'wagon';
        }
        if (str_contains($normalized, 'crossover')) {
            $tokens[] = 'crossover';
        }
        if (str_contains($normalized, 'coupe')) {
            $tokens[] = 'coupe';
        }
        if (str_contains($normalized, 'sport') && str_contains($normalized, 'coupe')) {
            $tokens[] = 'sport';
        }
        if (str_contains($normalized, 'truck') || str_contains($normalized, 'pickup')) {
            $tokens[] = 'pickup';
        }
        if (str_contains($normalized, 'hatchback') || str_contains($normalized, 'compact')) {
            $tokens[] = 'compact';
        }

        return array_values(array_unique($tokens));
    }

    private function listingsPriceSqlExpression(): string
    {
        return "CAST(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(price, ''), 'From', ''), 'from', ''), '$', ''), ',', ''), 'PKR', ''), 'AED', ''), 'USD', ''), '/mo', ''), 'Starting at', ''), 'starting at', ''), ' ', '') AS DECIMAL(15,2))";
    }

    /**
     * Build a small preference profile for car recommendations without affecting other modules.
     *
     * @return array{city_ids: array<int, int>, favorite_listing_ids: array<int, int>, own_listing_ids: array<int, int>}
     */
    private function carRecommendationSignals(Listing $listing): array
    {
        $cityIds = [];
        $favoriteListingIds = [];
        $ownListingIds = [];

        if ($listing->city_id) {
            $cityIds[] = (int) $listing->city_id;
        }

        if (! Auth::check()) {
            return [
                'city_ids' => array_values(array_unique(array_filter($cityIds))),
                'favorite_listing_ids' => [],
                'own_listing_ids' => [],
            ];
        }

        $userId = (int) Auth::id();

        $ownListingIds = Listing::query()
            ->where('user_id', $userId)
            ->whereRaw('LOWER(TRIM(module)) = ?', ['cars'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (! empty($ownListingIds)) {
            $ownCityIds = Listing::query()
                ->whereIn('id', $ownListingIds)
                ->whereNotNull('city_id')
                ->pluck('city_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $cityIds = array_merge($cityIds, $ownCityIds);
        }

        if (Schema::hasTable('favorites')) {
            $favoriteListingIds = DB::table('favorites')
                ->join('listings', 'favorites.listing_id', '=', 'listings.id')
                ->where('favorites.user_id', $userId)
                ->whereRaw('LOWER(TRIM(listings.module)) = ?', ['cars'])
                ->pluck('favorites.listing_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (! empty($favoriteListingIds)) {
                $favoriteCityIds = Listing::query()
                    ->whereIn('id', $favoriteListingIds)
                    ->whereNotNull('city_id')
                    ->pluck('city_id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
                $cityIds = array_merge($cityIds, $favoriteCityIds);
            }
        }

        return [
            'city_ids' => array_values(array_unique(array_filter($cityIds))),
            'favorite_listing_ids' => array_values(array_unique(array_filter($favoriteListingIds))),
            'own_listing_ids' => array_values(array_unique(array_filter($ownListingIds))),
        ];
    }

    /**
     * Some older data uses singular module values ("contractor") while routes use plural ("contractors").
     * Keep the public API tolerant to both, so local/live datasets behave consistently.
     *
     * @return array<int, string>
     */
    private function moduleAliases(string $moduleNormalized): array
    {
        $moduleNormalized = strtolower(trim($moduleNormalized));

        return match ($moduleNormalized) {
            'contractors' => ['contractors', 'contractor'],
            'restaurants' => ['restaurants', 'restaurant'],
            'cars' => ['cars', 'car'],
            'real-estate' => ['real-estate', 'real_estate', 'realestate', 'property', 'properties'],
            default => $moduleNormalized === '' ? [] : [$moduleNormalized],
        };
    }

    public function index(Request $request): JsonResponse
    {
        $module = 'contractors';
        $moduleNormalized = 'contractors';
        $moduleAliases = $this->moduleAliases($moduleNormalized);
        $perPage = min(max((int) $request->integer('per_page', 8), 1), 24);
        $budgetMax = (int) $request->integer('budget_max', 0);
        $listingType = strtolower(trim($request->string('listing_type')->toString()));
        $priceMin = (int) $request->integer('price_min', 0);
        $priceMax = (int) $request->integer('price_max', 0);
        $radiusMax = max(0, (int) preg_replace('/\D+/', '', $request->string('radius')->toString()));
        $categoryValues = collect(explode(',', $request->string('category')->toString()))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values();
        $bedrooms = max(0, (int) $request->integer('bedrooms', 0));
        $bathrooms = max(0, (int) $request->integer('bathrooms', 0));
        $areaMinSqm = max(0, (int) $request->integer('area_min', 0));
        $areaMaxSqm = max(0, (int) $request->integer('area_max', 0));
        $areaMinSqft = $areaMinSqm > 0 ? (int) ceil($areaMinSqm * 10.7639) : 0;
        $areaMaxSqft = $areaMaxSqm > 0 ? (int) ceil($areaMaxSqm * 10.7639) : 0;
        $yearBuiltMin = max(0, (int) $request->integer('year_built_min', 0));
        $yearBuiltMax = max(0, (int) $request->integer('year_built_max', 0));
        $carYearMin = max(0, (int) $request->integer('year_min', 0));
        $carYearMax = max(0, (int) $request->integer('year_max', 0));
        $carMake = trim($request->string('make')->toString());
        $carModel = trim($request->string('model')->toString());
        $carMileageMin = max(0, (int) $request->integer('mileage_min', 0));
        $carMileageMax = max(0, (int) $request->integer('mileage_max', 0));
        $carBodyTypes = $this->normalizedCsvValues($request->string('body_type')->toString());
        $carDriveTypes = $this->normalizedCsvValues($request->string('drive_type')->toString());
        $carFuelTypes = $this->normalizedCsvValues($request->string('fuel_type')->toString());
        $carTransmissions = $this->normalizedCsvValues($request->string('transmission')->toString());

        $state = strtoupper(trim($request->string('state')->toString()));

        $with = [
            'category:id,name,slug',
            'city:id,name,slug',
        ];

        if (Schema::hasTable('car_details') && Schema::hasColumn('car_details', 'year')) {
            $cols = ['listing_id', 'year'];
            if (Schema::hasColumn('car_details', 'brand')) {
                $cols[] = 'brand';
            }
            if (Schema::hasColumn('car_details', 'model')) {
                $cols[] = 'model';
            }
            if (Schema::hasColumn('car_details', 'condition')) {
                $cols[] = 'condition';
            }
            if (Schema::hasColumn('car_details', 'mileage')) {
                $cols[] = 'mileage';
            }
            if (Schema::hasColumn('car_details', 'drive_type')) {
                $cols[] = 'drive_type';
            }
            if (Schema::hasColumn('car_details', 'fuel_type')) {
                $cols[] = 'fuel_type';
            }
            if (Schema::hasColumn('car_details', 'transmission')) {
                $cols[] = 'transmission';
            }
            if (Schema::hasColumn('car_details', 'body_type')) {
                $cols[] = 'body_type';
            }
            if (Schema::hasColumn('car_details', 'is_verified')) {
                $cols[] = 'is_verified';
            }
            $with[] = 'carDetail:' . implode(',', $cols);
        }

        if ($moduleNormalized === 'real-estate' && Schema::hasTable('property_details')) {
            $propertyCols = ['listing_id'];
            foreach (['property_type', 'bedrooms', 'bathrooms', 'area_sqft', 'listing_type', 'wizard_data'] as $col) {
                if (Schema::hasColumn('property_details', $col)) {
                    $propertyCols[] = $col;
                }
            }
            $with[] = 'propertyDetail:' . implode(',', array_unique($propertyCols));
        }

        if (
            Schema::hasTable('event_details')
            && Schema::hasColumn('event_details', 'starts_at')
            && Schema::hasColumn('event_details', 'ends_at')
            && Schema::hasColumn('event_details', 'venue')
            && Schema::hasColumn('event_details', 'capacity')
        ) {
            $with[] = 'eventDetail:listing_id,starts_at,ends_at,venue,capacity';
        }

        $query = Listing::query()
            ->with($with)
            // Be tolerant to legacy data like "Published" or trailing spaces.
            ->whereRaw('LOWER(TRIM(status)) = ?', ['published']);

        if ($module !== '' && count($moduleAliases) > 0) {
            $query->where(function ($builder) use ($moduleAliases): void {
                foreach ($moduleAliases as $alias) {
                    $builder->orWhereRaw('LOWER(TRIM(module)) = ?', [$alias]);
                }
            });
        }

        if (
            $moduleNormalized === 'real-estate'
            && in_array($listingType, ['rent', 'sale'], true)
            && Schema::hasTable('property_details')
            && Schema::hasColumn('property_details', 'listing_type')
        ) {
            $query->whereHas('propertyDetail', fn ($builder) => $builder->where('listing_type', $listingType));
        }

        if ($categoryValues->isNotEmpty()) {
            $query->whereHas('category', function ($builder) use ($categoryValues): void {
                if ($categoryValues->count() === 1) {
                    $builder->where('slug', $categoryValues->first());
                    return;
                }

                $builder->whereIn('slug', $categoryValues->all());
            });
        }

        $hasCityStateCode = Schema::hasTable('cities') && Schema::hasColumn('cities', 'state_code');
        $selectedCitySlug = trim($request->string('city')->toString());

        if ($state !== '' && preg_match('/^[A-Z]{2}$/', $state) === 1 && $hasCityStateCode) {
            $query->whereHas('city', function ($builder) use ($state, $selectedCitySlug): void {
                $builder->where(function ($cityScope) use ($state, $selectedCitySlug): void {
                    $cityScope->whereRaw('UPPER(TRIM(state_code)) = ?', [$state]);

                    // Backward compatibility: some legacy listings still point to old
                    // city rows imported before state codes existed. When a concrete city
                    // is selected, let those rows pass as long as the slug matches.
                    if ($selectedCitySlug !== '') {
                        $cityScope->orWhere(function ($legacyScope) use ($selectedCitySlug): void {
                            $legacyScope
                                ->where('slug', $selectedCitySlug)
                                ->where(function ($nullState) {
                                    $nullState->whereNull('state_code')->orWhereRaw("TRIM(state_code) = ''");
                                });
                        });
                    }
                });
            });
        }

        if ($request->filled('city')) {
            $query->whereHas('city', function ($builder) use ($selectedCitySlug): void {
                $builder->where('slug', $selectedCitySlug);
            });
        }

        if (
            $moduleNormalized === 'cars'
            && $request->filled('stock')
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'year')
        ) {
            $stock = strtolower(trim($request->string('stock')->toString()));
            if (Schema::hasColumn('car_details', 'condition')) {
                $newYearThreshold = (int) now()->format('Y') - 3;
                if ($stock === 'new') {
                    $query->whereHas('carDetail', function ($builder) use ($newYearThreshold) {
                        $builder->where(function ($inner) use ($newYearThreshold) {
                            $inner->whereRaw('LOWER(TRIM(`condition`)) LIKE ?', ['new%'])
                                ->orWhere(function ($fallback) use ($newYearThreshold) {
                                    $fallback->where(function ($missing) {
                                        $missing->whereNull('condition')->orWhereRaw("TRIM(`condition`) = ''");
                                    })->where('year', '>=', $newYearThreshold);
                                });
                        });
                    });
                } elseif ($stock === 'used') {
                    $query->whereHas('carDetail', function ($builder) use ($newYearThreshold) {
                        $builder->where(function ($inner) use ($newYearThreshold) {
                            $inner->whereRaw('LOWER(TRIM(`condition`)) LIKE ?', ['used%'])
                                ->orWhere(function ($fallback) use ($newYearThreshold) {
                                    $fallback->where(function ($missing) {
                                        $missing->whereNull('condition')->orWhereRaw("TRIM(`condition`) = ''");
                                    })->where('year', '<', $newYearThreshold);
                                });
                        });
                    });
                }
            } else {
                $newYearThreshold = (int) now()->format('Y') - 3;
                if ($stock === 'new') {
                    $query->whereHas('carDetail', fn ($builder) => $builder->where('year', '>=', $newYearThreshold));
                } elseif ($stock === 'used') {
                    $query->whereHas('carDetail', fn ($builder) => $builder->where('year', '<', $newYearThreshold));
                }
            }
        }

        if (
            $moduleNormalized === 'cars'
            && $radiusMax > 0
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'radius')
        ) {
            $query->whereHas('carDetail', function ($builder) use ($radiusMax): void {
                $builder->where(function ($radiusScope) use ($radiusMax): void {
                    $normalizedRadiusSql = "CAST(REPLACE(REPLACE(LOWER(TRIM(radius)), 'mi', ''), ' ', '') AS UNSIGNED)";
                    $radiusScope
                        ->whereNull('radius')
                        ->orWhereRaw("TRIM(radius) = ''")
                        ->orWhereRaw("{$normalizedRadiusSql} <= ?", [$radiusMax]);
                });
            });
        }

        $carFiltersBaseQuery = null;
        if ($moduleNormalized === 'cars') {
            $carFiltersBaseQuery = clone $query;
        }

        if (
            $moduleNormalized === 'cars'
            && ($carYearMin > 0 || $carYearMax > 0)
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'year')
        ) {
            $query->whereHas('carDetail', function ($builder) use ($carYearMin, $carYearMax): void {
                if ($carYearMin > 0) {
                    $builder->where('year', '>=', $carYearMin);
                }
                if ($carYearMax > 0) {
                    $builder->where('year', '<=', $carYearMax);
                }
            });
        }

        if ($moduleNormalized === 'cars' && $carMake !== '') {
            $this->applyCarsMakeFilter($query, $carMake);
        }

        if ($moduleNormalized === 'cars' && $carModel !== '') {
            $this->applyCarsModelFilter($query, $carModel, $carMake);
        }

        if (
            $moduleNormalized === 'cars'
            && ($carMileageMin > 0 || $carMileageMax > 0)
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'mileage')
        ) {
            $query->whereHas('carDetail', function ($builder) use ($carMileageMin, $carMileageMax): void {
                if ($carMileageMin > 0) {
                    $builder->where('mileage', '>=', $carMileageMin);
                }
                if ($carMileageMax > 0) {
                    $builder->where('mileage', '<=', $carMileageMax);
                }
            });
        }

        if (
            $moduleNormalized === 'cars'
            && $carBodyTypes !== []
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'body_type')
        ) {
            $query->whereHas('carDetail', function ($builder) use ($carBodyTypes): void {
                $builder->where(function ($bodyScope) use ($carBodyTypes): void {
                    foreach ($carBodyTypes as $bodyType) {
                        if ($bodyType === 'pickup') {
                            $bodyScope->orWhereRaw('LOWER(TRIM(COALESCE(body_type, ""))) LIKE ?', ['%truck%'])
                                ->orWhereRaw('LOWER(TRIM(COALESCE(body_type, ""))) LIKE ?', ['%pickup%']);
                        } elseif ($bodyType === 'compact') {
                            $bodyScope->orWhereRaw('LOWER(TRIM(COALESCE(body_type, ""))) LIKE ?', ['%hatchback%'])
                                ->orWhereRaw('LOWER(TRIM(COALESCE(body_type, ""))) LIKE ?', ['%compact%']);
                        } elseif ($bodyType === 'sport') {
                            $bodyScope->orWhereRaw('LOWER(TRIM(COALESCE(body_type, ""))) LIKE ?', ['%sport coupe%']);
                        } else {
                            $bodyScope->orWhereRaw('LOWER(TRIM(COALESCE(body_type, ""))) LIKE ?', ['%' . $bodyType . '%']);
                        }
                    }
                });
            });
        }

        if (
            $moduleNormalized === 'cars'
            && $carDriveTypes !== []
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'drive_type')
        ) {
            $query->whereHas('carDetail', function ($builder) use ($carDriveTypes): void {
                $builder->where(function ($driveScope) use ($carDriveTypes): void {
                    foreach ($carDriveTypes as $driveType) {
                        if ($driveType === 'awd') {
                            $driveScope->orWhereRaw('LOWER(TRIM(COALESCE(drive_type, ""))) LIKE ?', ['%awd%'])
                                ->orWhereRaw('LOWER(TRIM(COALESCE(drive_type, ""))) LIKE ?', ['%4wd%'])
                                ->orWhereRaw('LOWER(TRIM(COALESCE(drive_type, ""))) LIKE ?', ['%all wheel%']);
                        } elseif ($driveType === 'fwd') {
                            $driveScope->orWhereRaw('LOWER(TRIM(COALESCE(drive_type, ""))) LIKE ?', ['%fwd%'])
                                ->orWhereRaw('LOWER(TRIM(COALESCE(drive_type, ""))) LIKE ?', ['%front wheel%']);
                        } elseif ($driveType === 'rwd') {
                            $driveScope->orWhereRaw('LOWER(TRIM(COALESCE(drive_type, ""))) LIKE ?', ['%rwd%'])
                                ->orWhereRaw('LOWER(TRIM(COALESCE(drive_type, ""))) LIKE ?', ['%rear wheel%']);
                        }
                    }
                });
            });
        }

        if (
            $moduleNormalized === 'cars'
            && $carFuelTypes !== []
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'fuel_type')
        ) {
            $query->whereHas('carDetail', function ($builder) use ($carFuelTypes): void {
                $builder->where(function ($fuelScope) use ($carFuelTypes): void {
                    foreach ($carFuelTypes as $fuelType) {
                        if ($fuelType === 'gas') {
                            $fuelScope->orWhereRaw('LOWER(TRIM(COALESCE(fuel_type, ""))) LIKE ?', ['%gasoline%'])
                                ->orWhereRaw('LOWER(TRIM(COALESCE(fuel_type, ""))) LIKE ?', ['%petrol%']);
                        } elseif ($fuelType === 'plugin') {
                            $fuelScope->orWhereRaw('LOWER(TRIM(COALESCE(fuel_type, ""))) LIKE ?', ['%plug%']);
                        } else {
                            $fuelScope->orWhereRaw('LOWER(TRIM(COALESCE(fuel_type, ""))) LIKE ?', ['%' . $fuelType . '%']);
                        }
                    }
                });
            });
        }

        if (
            $moduleNormalized === 'cars'
            && $carTransmissions !== []
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'transmission')
        ) {
            $query->whereHas('carDetail', function ($builder) use ($carTransmissions): void {
                $builder->where(function ($transmissionScope) use ($carTransmissions): void {
                    foreach ($carTransmissions as $transmission) {
                        if ($transmission === 'auto') {
                            $transmissionScope->orWhereRaw('LOWER(TRIM(COALESCE(transmission, ""))) LIKE ?', ['%automatic%']);
                        } elseif ($transmission === 'manual') {
                            $transmissionScope->orWhereRaw('LOWER(TRIM(COALESCE(transmission, ""))) LIKE ?', ['%manual%']);
                        }
                    }
                });
            });
        }

        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());

            if ($term !== '') {
                $query->where(function ($builder) use ($term): void {
                    $builder->where('title', 'like', "%{$term}%")
                        ->orWhere('excerpt', 'like', "%{$term}%")
                        ->orWhere('price', 'like', "%{$term}%")
                        ->orWhereHas('city', function ($cityQuery) use ($term): void {
                            $cityQuery->where('name', 'like', "%{$term}%")
                                ->orWhere('slug', 'like', "%{$term}%");
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($term): void {
                            $categoryQuery->where('name', 'like', "%{$term}%")
                                ->orWhere('slug', 'like', "%{$term}%");
                        });

                    if (Schema::hasTable('contractor_details') && Schema::hasColumn('contractor_details', 'service_area')) {
                        $builder->orWhereHas('contractorDetail', function ($detailQuery) use ($term): void {
                            $detailQuery->where('service_area', 'like', "%{$term}%");
                        });
                    }

                    if (Schema::hasTable('event_details') && Schema::hasColumn('event_details', 'venue')) {
                        $builder->orWhereHas('eventDetail', function ($detailQuery) use ($term): void {
                            $detailQuery->where('venue', 'like', "%{$term}%");
                        });
                    }
                });
            }
        }

        if (
            $moduleNormalized === 'real-estate'
            && Schema::hasTable('property_details')
        ) {
            if (Schema::hasColumn('property_details', 'bedrooms') && $bedrooms > 0) {
                $query->whereHas('propertyDetail', function ($builder) use ($bedrooms): void {
                    $builder->where('bedrooms', '>=', $bedrooms >= 4 ? 4 : $bedrooms);
                });
            }

            if (Schema::hasColumn('property_details', 'bathrooms') && $bathrooms > 0) {
                $query->whereHas('propertyDetail', function ($builder) use ($bathrooms): void {
                    $builder->where('bathrooms', '>=', $bathrooms >= 4 ? 4 : $bathrooms);
                });
            }

            if (Schema::hasColumn('property_details', 'area_sqft') && ($areaMinSqft > 0 || $areaMaxSqft > 0)) {
                $query->whereHas('propertyDetail', function ($builder) use ($areaMinSqft, $areaMaxSqft): void {
                    if ($areaMinSqft > 0) {
                        $builder->where('area_sqft', '>=', $areaMinSqft);
                    }
                    if ($areaMaxSqft > 0) {
                        $builder->where('area_sqft', '<=', $areaMaxSqft);
                    }
                });
            }

            if (Schema::hasColumn('property_details', 'wizard_data') && ($yearBuiltMin > 0 || $yearBuiltMax > 0)) {
                $yearPaths = [
                    '$."year-built"',
                    '$."year_built"',
                    '$."built_year"',
                    '$."construction_year"',
                    '$."year"',
                ];

                $query->whereHas('propertyDetail', function ($builder) use ($yearBuiltMin, $yearBuiltMax, $yearPaths): void {
                    $builder->where(function ($outer) use ($yearBuiltMin, $yearBuiltMax, $yearPaths): void {
                        foreach ($yearPaths as $path) {
                            $sql = "CAST(JSON_UNQUOTE(JSON_EXTRACT(wizard_data, '{$path}')) AS UNSIGNED)";
                            $parts = [];
                            $bindings = [];

                            if ($yearBuiltMin > 0) {
                                $parts[] = "{$sql} >= ?";
                                $bindings[] = $yearBuiltMin;
                            }
                            if ($yearBuiltMax > 0) {
                                $parts[] = "{$sql} <= ?";
                                $bindings[] = $yearBuiltMax;
                            }
                            if ($parts !== []) {
                                $outer->orWhereRaw(implode(' AND ', $parts), $bindings);
                            }
                        }
                    });
                });
            }
        }

        $ratingValues = collect(explode(',', $request->string('ratings')->toString()))
            ->map(fn (string $value) => (int) trim($value))
            ->filter(fn (int $value) => $value >= 1 && $value <= 5)
            ->values();

        if ($ratingValues->isNotEmpty()) {
            $query->where(function ($builder) use ($ratingValues): void {
                foreach ($ratingValues as $ratingValue) {
                    if ($ratingValue === 5) {
                        $builder->orWhere('rating', '>=', 4.5);
                        continue;
                    }

                    $min = max(0, $ratingValue - 0.5);
                    $max = $ratingValue + 0.5;
                    $builder->orWhereBetween('rating', [$min, $max]);
                }
            });
        }

        if ($request->boolean('availability')) {
            $query->where('availability_now', true);
        }

        $budgetValues = collect(explode(',', $request->string('budgets')->toString()))
            ->map(fn (string $value) => (int) trim($value))
            ->filter(fn (int $value) => $value >= 1 && $value <= 4)
            ->values();

        if ($budgetValues->isNotEmpty()) {
            $query->whereIn('budget_tier', $budgetValues->all());
        }

        $hasPriceAmount = Schema::hasColumn('listings', 'price_amount');
        $hasPriceColumn = Schema::hasColumn('listings', 'price');
        $priceSql = $this->listingsPriceSqlExpression();

        if ($budgetMax > 0) {
            $query->where(function ($builder) use ($hasPriceAmount, $hasPriceColumn, $budgetMax, $priceSql): void {
                if ($hasPriceAmount) {
                    $builder->where(function ($inner) use ($budgetMax): void {
                        $inner->whereNotNull('price_amount')->where('price_amount', '<=', $budgetMax);
                    });
                }

                if ($hasPriceColumn) {
                    $method = $hasPriceAmount ? 'orWhereRaw' : 'whereRaw';
                    $builder->{$method}("{$priceSql} <= ?", [$budgetMax]);
                }
            });
        }

        if ($budgetMax <= 0 && ($priceMin > 0 || $priceMax > 0)) {
            $query->where(function ($builder) use ($hasPriceAmount, $hasPriceColumn, $priceMin, $priceMax, $priceSql): void {
                if ($hasPriceAmount) {
                    $builder->where(function ($inner) use ($priceMin, $priceMax): void {
                        $inner->whereNotNull('price_amount');
                        if ($priceMin > 0) {
                            $inner->where('price_amount', '>=', $priceMin);
                        }
                        if ($priceMax > 0) {
                            $inner->where('price_amount', '<=', $priceMax);
                        }
                    });
                }

                if ($hasPriceColumn) {
                    $parts = [];
                    $bindings = [];
                    if ($priceMin > 0) {
                        $parts[] = "{$priceSql} >= ?";
                        $bindings[] = $priceMin;
                    }
                    if ($priceMax > 0) {
                        $parts[] = "{$priceSql} <= ?";
                        $bindings[] = $priceMax;
                    }
                    if (! empty($parts)) {
                        $method = $hasPriceAmount ? 'orWhereRaw' : 'whereRaw';
                        $builder->{$method}(implode(' AND ', $parts), $bindings);
                    }
                }
            });
        }

        $featureValues = collect(explode(',', $request->string('features')->toString()))
            ->map(fn (string $value) => trim($value))
            ->filter(fn (string $value) => $value !== '')
            ->values();

        if (Schema::hasColumn('listings', 'features')) {
            foreach ($featureValues as $featureValue) {
                /**
                 * Shared hosting often runs older MySQL/MariaDB versions where JSON_CONTAINS isn't available.
                 * Using a quoted LIKE keeps filtering working across more platforms.
                 */
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $featureValue);
                $pattern = '%"' . $escaped . '"%';
                $query->whereRaw("features LIKE ? ESCAPE '\\\\'", [$pattern]);
            }
        }

        $sort = strtolower(trim($request->string('sort')->toString()));

        if ($sort === 'rating') {
            $query->orderByDesc('rating')->orderByDesc('reviews_count')->latest('id');
        } elseif ($sort === 'price_asc') {
            if ($hasPriceAmount) {
                $query->orderByRaw('COALESCE(price_amount, 999999999) asc')->latest('id');
            } elseif ($hasPriceColumn) {
                $query->orderByRaw("{$priceSql} asc")->latest('id');
            } else {
                $query->latest('published_at')->latest('id');
            }
        } elseif ($sort === 'price_desc') {
            if ($hasPriceAmount) {
                $query->orderByRaw('COALESCE(price_amount, 0) desc')->latest('id');
            } elseif ($hasPriceColumn) {
                $query->orderByRaw("{$priceSql} desc")->latest('id');
            } else {
                $query->latest('published_at')->latest('id');
            }
        } else {
            $query->latest('published_at')->latest('id');
        }

        $paginated = $query->paginate($perPage)->withQueryString();

        $carFilters = [
            'years' => [],
            'makes' => [],
            'models' => [],
            'body_types' => [],
            'drive_types' => [],
            'fuel_types' => [],
            'transmissions' => [],
        ];

        if (
            $moduleNormalized === 'cars'
            && $carFiltersBaseQuery
            && Schema::hasTable('car_details')
            && Schema::hasColumn('car_details', 'year')
        ) {
            $carFilterSelect = ['listings.id', 'listings.title'];
            $carFilterRelationCols = ['listing_id', 'year'];

            if (Schema::hasColumn('car_details', 'brand')) {
                $carFilterRelationCols[] = 'brand';
            }
            if (Schema::hasColumn('car_details', 'model')) {
                $carFilterRelationCols[] = 'model';
            }
            if (Schema::hasColumn('car_details', 'drive_type')) {
                $carFilterRelationCols[] = 'drive_type';
            }
            if (Schema::hasColumn('car_details', 'fuel_type')) {
                $carFilterRelationCols[] = 'fuel_type';
            }
            if (Schema::hasColumn('car_details', 'transmission')) {
                $carFilterRelationCols[] = 'transmission';
            }
            if (Schema::hasColumn('car_details', 'body_type')) {
                $carFilterRelationCols[] = 'body_type';
            }

            $baseCarsForFilters = (clone $carFiltersBaseQuery)
                ->select($carFilterSelect)
                ->with(['carDetail:' . implode(',', $carFilterRelationCols)])
                ->get();

            $carsForModelsQuery = clone $carFiltersBaseQuery;

            if ($carYearMin > 0 || $carYearMax > 0) {
                $carsForModelsQuery->whereHas('carDetail', function ($builder) use ($carYearMin, $carYearMax): void {
                    if ($carYearMin > 0) {
                        $builder->where('year', '>=', $carYearMin);
                    }
                    if ($carYearMax > 0) {
                        $builder->where('year', '<=', $carYearMax);
                    }
                });
            }

            if ($carMake !== '') {
                $this->applyCarsMakeFilter($carsForModelsQuery, $carMake);
            }

            $carsForModels = $carsForModelsQuery
                ->select($carFilterSelect)
                ->with(['carDetail:' . implode(',', $carFilterRelationCols)])
                ->get();

            $carFilters['years'] = $baseCarsForFilters
                ->map(fn (Listing $listing) => (int) ($listing->carDetail?->year ?? 0))
                ->filter(fn (int $year) => $year > 0)
                ->unique()
                ->sortDesc()
                ->values()
                ->map(fn (int $year) => [
                    'value' => (string) $year,
                    'label' => (string) $year,
                ])
                ->all();

            $carFilters['makes'] = $baseCarsForFilters
                ->map(function (Listing $listing): array {
                    $identity = $this->inferCarIdentity(
                        $listing->carDetail?->brand,
                        $listing->carDetail?->model,
                        $listing->title
                    );

                    return [
                        'sort' => strtolower($identity['make']),
                        'label' => $identity['make'],
                    ];
                })
                ->filter(fn (array $item) => $item['label'] !== '')
                ->unique('sort')
                ->sortBy('sort')
                ->values()
                ->map(fn (array $item) => [
                    'value' => $item['label'],
                    'label' => $item['label'],
                ])
                ->all();

            $carFilters['models'] = $carsForModels
                ->map(function (Listing $listing): array {
                    $identity = $this->inferCarIdentity(
                        $listing->carDetail?->brand,
                        $listing->carDetail?->model,
                        $listing->title
                    );

                    return [
                        'sort' => strtolower($identity['model']),
                        'label' => $identity['model'],
                    ];
                })
                ->filter(fn (array $item) => $item['label'] !== '')
                ->unique('sort')
                ->sortBy('sort')
                ->values()
                ->map(fn (array $item) => [
                    'value' => $item['label'],
                    'label' => $item['label'],
                ])
                ->all();

            $driveCounts = ['awd' => 0, 'fwd' => 0, 'rwd' => 0];
            $bodyTypeCounts = ['sedan' => 0, 'suv' => 0, 'wagon' => 0, 'crossover' => 0, 'coupe' => 0, 'pickup' => 0, 'sport' => 0, 'compact' => 0];
            $fuelCounts = ['gas' => 0, 'diesel' => 0, 'electric' => 0, 'hybrid' => 0, 'plugin' => 0, 'hydrogen' => 0];
            $transmissionCounts = ['auto' => 0, 'manual' => 0];

            foreach ($baseCarsForFilters as $listing) {
                foreach ($this->carBodyTypeTokens($listing->carDetail?->body_type) as $token) {
                    if (array_key_exists($token, $bodyTypeCounts)) {
                        $bodyTypeCounts[$token] += 1;
                    }
                }

                foreach ($this->carDriveTypeTokens($listing->carDetail?->drive_type) as $token) {
                    if (array_key_exists($token, $driveCounts)) {
                        $driveCounts[$token] += 1;
                    }
                }

                foreach ($this->carFuelTokens($listing->carDetail?->fuel_type) as $token) {
                    if (array_key_exists($token, $fuelCounts)) {
                        $fuelCounts[$token] += 1;
                    }
                }

                foreach ($this->carTransmissionTokens($listing->carDetail?->transmission) as $token) {
                    if (array_key_exists($token, $transmissionCounts)) {
                        $transmissionCounts[$token] += 1;
                    }
                }
            }

            $carFilters['drive_types'] = collect([
                ['value' => 'awd', 'label' => 'AWD/4WD'],
                ['value' => 'fwd', 'label' => 'Front Wheel Drive'],
                ['value' => 'rwd', 'label' => 'Rear Wheel Drive'],
            ])->map(fn (array $item) => $item + ['count' => $driveCounts[$item['value']] ?? 0])->all();

            $carFilters['body_types'] = collect([
                ['value' => 'sedan', 'label' => 'Sedan'],
                ['value' => 'suv', 'label' => 'SUV'],
                ['value' => 'wagon', 'label' => 'Wagon'],
                ['value' => 'crossover', 'label' => 'Crossover'],
                ['value' => 'coupe', 'label' => 'Coupe'],
                ['value' => 'pickup', 'label' => 'Pickup'],
                ['value' => 'sport', 'label' => 'Sport Coupe'],
                ['value' => 'compact', 'label' => 'Compact'],
            ])->map(fn (array $item) => $item + ['count' => $bodyTypeCounts[$item['value']] ?? 0])->all();

            $carFilters['fuel_types'] = collect([
                ['value' => 'gas', 'label' => 'Gasoline'],
                ['value' => 'diesel', 'label' => 'Diesel'],
                ['value' => 'electric', 'label' => 'Electric'],
                ['value' => 'hybrid', 'label' => 'Hybrid'],
                ['value' => 'plugin', 'label' => 'Plug-in Hybrid'],
                ['value' => 'hydrogen', 'label' => 'Hydrogen'],
            ])->map(fn (array $item) => $item + ['count' => $fuelCounts[$item['value']] ?? 0])->all();

            $carFilters['transmissions'] = collect([
                ['value' => 'auto', 'label' => 'Automatic'],
                ['value' => 'manual', 'label' => 'Manual'],
            ])->map(fn (array $item) => $item + ['count' => $transmissionCounts[$item['value']] ?? 0])->all();
        }

        $categoryCounts = Listing::query()
            ->select('category_id', DB::raw('COUNT(*) as total'))
            ->whereRaw('LOWER(TRIM(status)) = ?', ['published'])
            ->when($module !== '' && count($moduleAliases) > 0, function ($builder) use ($moduleAliases): void {
                $builder->where(function ($inner) use ($moduleAliases): void {
                    foreach ($moduleAliases as $alias) {
                        $inner->orWhereRaw('LOWER(TRIM(module)) = ?', [$alias]);
                    }
                });
            })
            ->when(
                $moduleNormalized === 'real-estate'
                && in_array($listingType, ['rent', 'sale'], true)
                && Schema::hasTable('property_details')
                && Schema::hasColumn('property_details', 'listing_type'),
                fn ($builder) => $builder->whereHas('propertyDetail', fn ($detail) => $detail->where('listing_type', $listingType))
            )
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categories = Category::query()
            ->when($module !== '' && count($moduleAliases) > 0, function ($builder) use ($moduleAliases): void {
                $builder->where(function ($inner) use ($moduleAliases): void {
                    foreach ($moduleAliases as $alias) {
                        $inner->orWhereRaw('LOWER(TRIM(module)) = ?', [$alias]);
                    }
                });
            })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category) => [
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => (int) ($categoryCounts[$category->id] ?? 0),
            ])
            ->values();

        /**
         * Listings pages already render their own city dropdown markup, so sending
         * the full cities table here makes the API response unnecessarily huge.
         * That can slow down or break listings pages on production.
         *
         * Keep this light by only returning the selected city (if any), which is
         * enough for active pill labels and state hydration.
         */
        $cities = collect();
        if ($request->filled('city')) {
            $selectedCitySlug = trim($request->string('city')->toString());
            if ($selectedCitySlug !== '') {
                $cities = City::query()
                    ->where('is_active', true)
                    ->where('slug', $selectedCitySlug)
                    ->orderBy('name')
                    ->get(['name', 'slug'])
                    ->map(fn (City $city) => [
                        'name' => $city->name,
                        'slug' => $city->slug,
                    ])
                    ->values();
            }
        }

        return response()->json([
            'data' => $paginated->getCollection()->map(function (Listing $listing) {
                $excerpt = (string) ($listing->excerpt ?? '');

                if ($listing->module === 'restaurants') {
                    $decoded = json_decode($excerpt, true);
                    if (is_array($decoded) && ($decoded['_mc_restaurant_v1'] ?? false)) {
                        $cuisine = (string) ($listing->category?->name ?? '');
                        $cityName = (string) ($listing->city?->name ?? '');
                        $bits = array_values(array_filter([
                            $cuisine !== '' ? "{$cuisine} restaurant" : 'Restaurant',
                            $cityName !== '' ? "in {$cityName}" : '',
                        ]));
                        $excerpt = $bits ? (implode(' ', $bits) . '.') : '';
                    }
                }

                return [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'slug' => $listing->slug,
                    'module' => $listing->module,
                    'published_at' => optional($listing->published_at)->toDateTimeString(),
                    'excerpt' => $excerpt,
                    'price' => $listing->display_price,
                    'budget_tier' => (int) $listing->budget_tier,
                    'availability_now' => (bool) $listing->availability_now,
                    'features' => (function () use ($listing) {
                        if ($listing->module === 'restaurants') {
                            return [];
                        }

                        $features = array_values($listing->features ?? []);

                        if (
                            $listing->module === 'cars'
                            && $listing->carDetail
                            && Schema::hasColumn('car_details', 'is_verified')
                            && (bool) $listing->carDetail->is_verified
                        ) {
                            $features[] = 'verified';
                        }

                        return array_values(array_unique(array_filter($features)));
                    })(),
                    'rating' => (float) $listing->rating,
                    'reviews_count' => (int) $listing->reviews_count,
                    'image_url' => $listing->image_url,
                    'category' => [
                        'name' => $listing->category?->name,
                        'slug' => $listing->category?->slug,
                    ],
                    'city' => [
                        'name' => $listing->city?->name,
                        'slug' => $listing->city?->slug,
                    ],
                    'details' => [
                        'property' => $listing->propertyDetail ? [
                            'property_type' => $listing->propertyDetail->property_type,
                            'listing_type' => $listing->propertyDetail->listing_type,
                            'bedrooms' => $listing->propertyDetail->bedrooms,
                            'bathrooms' => $listing->propertyDetail->bathrooms,
                            'area_sqft' => $listing->propertyDetail->area_sqft,
                        ] : null,
                        'car' => $listing->carDetail ? [
                            'brand' => Schema::hasColumn('car_details', 'brand') ? $listing->carDetail->brand : null,
                            'model' => Schema::hasColumn('car_details', 'model') ? $listing->carDetail->model : null,
                            'year' => $listing->carDetail->year,
                            'condition' => Schema::hasColumn('car_details', 'condition') ? $listing->carDetail->condition : null,
                            'mileage' => Schema::hasColumn('car_details', 'mileage') ? $listing->carDetail->mileage : null,
                            'drive_type' => Schema::hasColumn('car_details', 'drive_type') ? $listing->carDetail->drive_type : null,
                            'fuel_type' => Schema::hasColumn('car_details', 'fuel_type') ? $listing->carDetail->fuel_type : null,
                            'transmission' => Schema::hasColumn('car_details', 'transmission') ? $listing->carDetail->transmission : null,
                            'body_type' => Schema::hasColumn('car_details', 'body_type') ? $listing->carDetail->body_type : null,
                            'is_verified' => Schema::hasColumn('car_details', 'is_verified') ? (bool) $listing->carDetail->is_verified : false,
                        ] : null,
                    ],
                ];
            })->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'filters' => [
                'categories' => $categories,
                'cities' => $cities,
                'cars' => $carFilters,
            ],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $module = 'contractors';
        $moduleNormalized = 'contractors';
        $moduleAliases = $this->moduleAliases($moduleNormalized);

        abort_unless(in_array($module, array_keys(Listing::MODULE_OPTIONS), true), 404);

        $query = Listing::query()
            ->with([
                'category:id,name,slug',
                'city:id,name,slug',
                'images:id,listing_id,image_path,sort_order,is_cover',
                'contractorDetail',
                'propertyDetail',
                'carDetail',
                'eventDetail',
            ])
            // Tolerate older data variations ("Published", whitespace, etc.)
            ->whereRaw('LOWER(TRIM(status)) = ?', ['published']);

        if (count($moduleAliases) > 0) {
            $query->where(function ($builder) use ($moduleAliases): void {
                foreach ($moduleAliases as $alias) {
                    $builder->orWhereRaw('LOWER(TRIM(module)) = ?', [$alias]);
                }
            });
        }

        if ($request->filled('slug')) {
            $query->where('slug', $request->string('slug')->toString());
        }

        $listing = $query->latest('published_at')->latest('id')->firstOrFail();
        $isClaimed = ListingClaimRequest::query()
            ->where('listing_id', $listing->id)
            ->whereNotNull('used_at')
            ->exists();

        $excerpt = (string) ($listing->excerpt ?? '');
        $restaurantMeta = null;
        if ($module === 'restaurants') {
            $decoded = json_decode($excerpt, true);
            if (is_array($decoded) && ($decoded['_mc_restaurant_v1'] ?? false)) {
                $restaurantMeta = $decoded;

                // Avoid showing raw JSON in the "About" section if no human description was collected.
                $cuisine = (string) ($listing->category?->name ?? '');
                $cityName = (string) ($listing->city?->name ?? '');
                $bits = array_values(array_filter([$cuisine !== '' ? "{$cuisine} restaurant" : 'Restaurant', $cityName !== '' ? "in {$cityName}" : '']));
                $excerpt = $bits ? (implode(' ', $bits) . '.') : '';
            }
        }

        $baseRelatedQuery = Listing::query()
            ->with(['category:id,name,slug', 'city:id,name,slug', 'carDetail:listing_id,year,condition'])
            ->whereRaw('LOWER(TRIM(status)) = ?', ['published'])
            ->whereKeyNot($listing->id)
            ->whereNotNull('slug');

        if (count($moduleAliases) > 0) {
            $baseRelatedQuery->where(function ($builder) use ($moduleAliases): void {
                foreach ($moduleAliases as $alias) {
                    $builder->orWhereRaw('LOWER(TRIM(module)) = ?', [$alias]);
                }
            });
        }

        if ($moduleNormalized === 'cars') {
            $signals = $this->carRecommendationSignals($listing);
            $preferredCityIds = $signals['city_ids'];
            $favoriteListingIds = $signals['favorite_listing_ids'];
            $ownListingIds = $signals['own_listing_ids'];
            $currentCondition = strtolower(trim((string) ($listing->carDetail?->condition ?? '')));
            $related = collect();
            $pickedIds = [$listing->id];

            if (! empty($ownListingIds)) {
                $pickedIds = array_values(array_unique(array_merge($pickedIds, $ownListingIds)));
            }

            $fetchCars = function ($query, int $limit = 4) use (&$pickedIds) {
                $items = (clone $query)
                    ->whereNotIn('id', $pickedIds)
                    ->latest('published_at')
                    ->latest('id')
                    ->take($limit)
                    ->get();

                if ($items->isNotEmpty()) {
                    $pickedIds = array_values(array_unique(array_merge($pickedIds, $items->pluck('id')->all())));
                }

                return $items;
            };

            if (! empty($favoriteListingIds)) {
                $related = $fetchCars((clone $baseRelatedQuery)->whereIn('id', $favoriteListingIds), 4);
            } elseif (! empty($preferredCityIds)) {
                $related = $fetchCars((clone $baseRelatedQuery)->whereIn('city_id', $preferredCityIds), 4);
            } elseif ($listing->category_id) {
                $related = $fetchCars((clone $baseRelatedQuery)->where('category_id', $listing->category_id), 4);
            } elseif ($currentCondition !== '' && Schema::hasTable('car_details') && Schema::hasColumn('car_details', 'condition')) {
                $related = $fetchCars(
                    (clone $baseRelatedQuery)->whereHas('carDetail', function ($builder) use ($currentCondition) {
                        $builder->whereRaw('LOWER(TRIM(COALESCE(`condition`, ""))) = ?', [$currentCondition]);
                    }),
                    4
                );
            } else {
                $related = $fetchCars(clone $baseRelatedQuery, 4);
            }

            if ($related->count() < 4) {
                $related = $related
                    ->concat($fetchCars(clone $baseRelatedQuery, 4 - $related->count()))
                    ->take(4)
                    ->values();
            }
        } else {
            $related = $baseRelatedQuery
                ->latest('published_at')
                ->latest('id')
                ->take(4)
                ->get();
        }

        $extraFeatures = [];
        if ($moduleNormalized === 'cars' && $listing->carDetail) {
            $wizard = is_array($listing->carDetail->wizard_data) ? $listing->carDetail->wizard_data : [];
            $wizardFeatures = $wizard['features'] ?? null;
            if (is_array($wizardFeatures)) {
                $extraFeatures = collect($wizardFeatures)
                    ->map(fn ($v) => trim((string) $v))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        $features = $moduleNormalized === 'restaurants'
            ? []
            : array_values(array_unique(array_filter(array_merge($listing->features ?? [], $extraFeatures))));

        if ($moduleNormalized === 'cars' && $listing->carDetail && Schema::hasColumn('car_details', 'is_verified') && (bool) $listing->carDetail->is_verified) {
            $features = array_values(array_unique(array_filter(array_merge($features, ['verified']))));
        }

        $propertyWizard = [];
        if ($moduleNormalized === 'real-estate' && $listing->propertyDetail) {
            $propertyWizard = is_array($listing->propertyDetail->wizard_data) ? $listing->propertyDetail->wizard_data : [];
        }
        $promotionPackage = strtolower(trim((string) ($propertyWizard['promotion_package'] ?? $propertyWizard['package'] ?? '')));
        $promotionPackageLabel = match ($promotionPackage) {
            'easy-start' => 'Easy Start',
            'fast-sale' => 'Fast Sale',
            'turbo-boost' => 'Turbo Boost',
            default => '',
        };
        $promotionPackagePrice = match ($promotionPackage) {
            'easy-start' => '$25 / month',
            'fast-sale' => '$49 / month',
            'turbo-boost' => '$70 / month',
            default => '',
        };
        $selectedPropertyServices = [];
        $selectedPropertyServicesDetails = [];
        if (! empty($propertyWizard['service_certify'])) {
            $selectedPropertyServices[] = 'Check and certify my ad by Monaclick experts';
            $selectedPropertyServicesDetails[] = [
                'label' => 'Check and certify my ad by Monaclick experts',
                'price' => '$35',
            ];
        }
        if (! empty($propertyWizard['service_lifts'])) {
            $selectedPropertyServices[] = '10 lifts to the top of the list (daily, 7 days)';
            $selectedPropertyServicesDetails[] = [
                'label' => '10 lifts to the top of the list (daily, 7 days)',
                'price' => '$29 / month',
            ];
        }
        if (! empty($propertyWizard['service_analytics'])) {
            $selectedPropertyServices[] = 'Detailed user engagement analytics';
            $selectedPropertyServicesDetails[] = [
                'label' => 'Detailed user engagement analytics',
                'price' => '$15 / month',
            ];
        }

        $selectedContractorServices = collect(is_array($features) ? $features : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($token) => str_starts_with(strtolower($token), 'service:'))
            ->map(fn ($token) => trim(substr($token, strlen('service:'))))
            ->filter()
            ->values()
            ->all();
        $contractorFeatureTokens = collect(is_array($features) ? $features : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter();
        $contractorAddressFallback = $contractorFeatureTokens
            ->first(fn ($token) => str_starts_with(strtolower($token), 'contractor-address:'));
        $contractorZipFallback = $contractorFeatureTokens
            ->first(fn ($token) => str_starts_with(strtolower($token), 'contractor-zip:'));
        $contractorStateFallback = $contractorFeatureTokens
            ->first(fn ($token) => str_starts_with(strtolower($token), 'contractor-state:'));
        $contractorAddressFallback = $contractorAddressFallback ? trim(substr($contractorAddressFallback, strlen('contractor-address:'))) : null;
        $contractorZipFallback = $contractorZipFallback ? trim(substr($contractorZipFallback, strlen('contractor-zip:'))) : null;
        $contractorStateFallback = $contractorStateFallback ? trim(substr($contractorStateFallback, strlen('contractor-state:'))) : null;

        return response()->json([
            'data' => [
                'id' => $listing->id,
                'title' => $listing->title,
                'slug' => $listing->slug,
                'module' => $listing->module,
                'excerpt' => $excerpt,
                'price' => $listing->display_price,
                'features' => $features,
                'rating' => (float) $listing->rating,
                'reviews_count' => (int) $listing->reviews_count,
                'image_url' => $listing->image_url,
                'category' => [
                    'name' => $listing->category?->name,
                    'slug' => $listing->category?->slug,
                ],
                'city' => [
                    'name' => $listing->city?->name,
                    'slug' => $listing->city?->slug,
                ],
                'images' => $listing->images->map(fn ($image) => [
                    'image_url' => $image->image_url,
                ])->values(),
                'details' => [
                    'contractor' => $listing->contractorDetail ? [
                        'service_area' => $listing->contractorDetail->service_area,
                        'address' => $this->resolvePreferredContractorAddress(
                            Schema::hasColumn('contractor_details', 'address_line')
                                ? $listing->contractorDetail->address_line
                                : null,
                            $contractorAddressFallback,
                            $listing->city?->name
                        ),
                        'zip_code' => $this->resolvePreferredContractorValue(
                            Schema::hasColumn('contractor_details', 'zip_code')
                                ? $listing->contractorDetail->zip_code
                                : null,
                            $contractorZipFallback
                        ),
                        'state' => $this->resolvePreferredContractorValue(
                            Schema::hasColumn('contractor_details', 'state_code')
                                ? $listing->contractorDetail->state_code
                                : null,
                            $contractorStateFallback
                        ),
                        'license_number' => $listing->contractorDetail->license_number,
                        'is_verified' => (bool) $listing->contractorDetail->is_verified,
                        'business_hours' => $listing->contractorDetail->business_hours,
                        'services_provided' => $selectedContractorServices,
                    ] : null,
                    'property' => $listing->propertyDetail ? [
                        'property_type' => $listing->propertyDetail->property_type,
                        'selected_type' => $propertyWizard['radio:type'] ?? null,
                        'listing_type' => $listing->propertyDetail->listing_type,
                        'promotion_package' => $promotionPackage,
                        'promotion_package_label' => $promotionPackageLabel,
                        'promotion_package_price' => $promotionPackagePrice,
                        'selected_services' => $selectedPropertyServices,
                        'selected_services_details' => $selectedPropertyServicesDetails,
                        'bedrooms' => $listing->propertyDetail->bedrooms,
                        'bathrooms' => $listing->propertyDetail->bathrooms,
                        'area_sqft' => $listing->propertyDetail->area_sqft,
                        'floors_total' => $propertyWizard['floors_total'] ?? null,
                        'floor' => $propertyWizard['floor'] ?? null,
                        'total_area' => $propertyWizard['total_area'] ?? ($propertyWizard['total-area'] ?? null),
                        'living_area' => $propertyWizard['living_area'] ?? null,
                        'kitchen_area' => $propertyWizard['kitchen_area'] ?? null,
                        'parking' => $propertyWizard['parking'] ?? null,
                        'district' => $propertyWizard['district'] ?? null,
                        'zip' => $propertyWizard['zip'] ?? null,
                        'address' => $propertyWizard['address'] ?? null,
                        'state' => $propertyWizard['state'] ?? null,
                        'contact_name' => trim(implode(' ', array_filter([(string) ($propertyWizard['fn'] ?? ''), (string) ($propertyWizard['ln'] ?? '')]))),
                        'contact_email' => $propertyWizard['email'] ?? null,
                        'contact_phone' => $propertyWizard['phone'] ?? null,
                    ] : null,
                    'car' => $listing->carDetail ? [
                        'brand' => $listing->carDetail->brand,
                        'model' => $listing->carDetail->model,
                        'condition' => $listing->carDetail->condition,
                        'year' => $listing->carDetail->year,
                        'mileage' => $listing->carDetail->mileage,
                        'radius' => $listing->carDetail->radius,
                        'drive_type' => $listing->carDetail->drive_type,
                        'engine' => $listing->carDetail->engine,
                        'fuel_type' => $listing->carDetail->fuel_type,
                        'transmission' => $listing->carDetail->transmission,
                        'body_type' => $listing->carDetail->body_type,
                        'city_mpg' => $listing->carDetail->city_mpg,
                        'highway_mpg' => $listing->carDetail->highway_mpg,
                        'exterior_color' => $listing->carDetail->exterior_color,
                        'interior_color' => $listing->carDetail->interior_color,
                        'seller_type' => $listing->carDetail->seller_type,
                        'contact_first_name' => $listing->carDetail->contact_first_name,
                        'contact_last_name' => $listing->carDetail->contact_last_name,
                        'contact_email' => $listing->carDetail->contact_email,
                        'contact_phone' => $listing->carDetail->contact_phone,
                        'is_verified' => Schema::hasColumn('car_details', 'is_verified') ? (bool) $listing->carDetail->is_verified : false,
                        'negotiated' => (bool) $listing->carDetail->negotiated,
                        'installments' => (bool) $listing->carDetail->installments,
                        'exchange' => (bool) $listing->carDetail->exchange,
                        'uncleared' => (bool) $listing->carDetail->uncleared,
                        'dealer_ready' => (bool) $listing->carDetail->dealer_ready,
                        'features' => $extraFeatures,
                    ] : null,
                    'event' => $listing->eventDetail ? [
                        'starts_at' => optional($listing->eventDetail->starts_at)->toIso8601String(),
                        'ends_at' => optional($listing->eventDetail->ends_at)->toIso8601String(),
                        'venue' => $listing->eventDetail->venue,
                        'capacity' => $listing->eventDetail->capacity,
                    ] : null,
                    'restaurant' => $restaurantMeta ? [
                        'address' => (string) ($restaurantMeta['address'] ?? ''),
                        'zip_code' => (string) ($restaurantMeta['zip_code'] ?? ''),
                        'country' => (string) ($restaurantMeta['country'] ?? ''),
                        'seating_capacity' => (string) ($restaurantMeta['seating_capacity'] ?? ''),
                        'services' => is_array($restaurantMeta['services'] ?? null) ? array_values($restaurantMeta['services']) : [],
                        'opening_hours' => is_array($restaurantMeta['opening_hours'] ?? null) ? $restaurantMeta['opening_hours'] : [],
                        'contact_name' => (string) ($restaurantMeta['contact_name'] ?? ''),
                        'phone' => (string) ($restaurantMeta['phone'] ?? ''),
                        'email' => (string) ($restaurantMeta['email'] ?? ''),
                    ] : null,
                ],
                'claim' => [
                    'eligible' => in_array($moduleNormalized, ['contractors', 'restaurants'], true),
                    'claimed' => $isClaimed,
                    'can_edit_profile' => Auth::check() && (int) Auth::id() === (int) $listing->user_id,
                    'edit_profile_url' => Auth::check() && (int) Auth::id() === (int) $listing->user_id
                        ? '/account/settings'
                        : null,
                    'url' => in_array($moduleNormalized, ['contractors', 'restaurants'], true) && ! empty($listing->slug)
                        ? '/claim/' . $moduleNormalized . '?slug=' . urlencode((string) $listing->slug)
                        : null,
                    'email' => trim((string) ($listing->claim_email
                        ?? ($restaurantMeta['email'] ?? null)
                        ?? ($propertyWizard['email'] ?? null)
                        ?? ($listing->carDetail->contact_email ?? null)
                        ?? ($listing->user?->email ?? ''))),
                ],
            ],
            'related' => $related->map(fn (Listing $item) => [
                'title' => $item->title,
                'slug' => $item->slug,
                'module' => $item->module,
                'image_url' => $item->image_url,
                'price' => $item->display_price,
                'rating' => (float) $item->rating,
                'reviews_count' => (int) $item->reviews_count,
                'city' => [
                    'name' => $item->city?->name,
                ],
                'details' => [
                    'car' => $item->carDetail ? [
                        'year' => $item->carDetail->year,
                        'condition' => $item->carDetail->condition,
                    ] : null,
                ],
            ])->values(),
        ]);
    }
}
