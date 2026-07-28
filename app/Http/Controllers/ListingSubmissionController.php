<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Models\ContractorDetail;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\PropertyDetail;
use App\Models\State;
use App\Services\SubscriptionSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ListingSubmissionController extends Controller
{
    private function normalizeUsStateCode(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]{2}$/', $raw) === 1) {
            return strtoupper($raw);
        }

        if (preg_match('/\\(([A-Za-z]{2})\\)\\s*$/', $raw, $m) === 1) {
            return strtoupper($m[1]);
        }

        $key = preg_replace('/\\([A-Za-z]{2}\\)\\s*$/', '', $raw) ?? $raw;
        $key = strtolower(trim(preg_replace('/\\s+/', ' ', $key) ?? $key));
        if ($key === '') {
            return '';
        }

        $map = [
            'alabama' => 'AL',
            'alaska' => 'AK',
            'arizona' => 'AZ',
            'arkansas' => 'AR',
            'california' => 'CA',
            'colorado' => 'CO',
            'connecticut' => 'CT',
            'delaware' => 'DE',
            'district of columbia' => 'DC',
            'florida' => 'FL',
            'georgia' => 'GA',
            'hawaii' => 'HI',
            'idaho' => 'ID',
            'illinois' => 'IL',
            'indiana' => 'IN',
            'iowa' => 'IA',
            'kansas' => 'KS',
            'kentucky' => 'KY',
            'louisiana' => 'LA',
            'maine' => 'ME',
            'maryland' => 'MD',
            'massachusetts' => 'MA',
            'michigan' => 'MI',
            'minnesota' => 'MN',
            'mississippi' => 'MS',
            'missouri' => 'MO',
            'montana' => 'MT',
            'nebraska' => 'NE',
            'nevada' => 'NV',
            'new hampshire' => 'NH',
            'new jersey' => 'NJ',
            'new mexico' => 'NM',
            'new york' => 'NY',
            'north carolina' => 'NC',
            'north dakota' => 'ND',
            'ohio' => 'OH',
            'oklahoma' => 'OK',
            'oregon' => 'OR',
            'pennsylvania' => 'PA',
            'rhode island' => 'RI',
            'south carolina' => 'SC',
            'south dakota' => 'SD',
            'tennessee' => 'TN',
            'texas' => 'TX',
            'utah' => 'UT',
            'vermont' => 'VT',
            'virginia' => 'VA',
            'washington' => 'WA',
            'west virginia' => 'WV',
            'wisconsin' => 'WI',
            'wyoming' => 'WY',
        ];

        return $map[$key] ?? '';
    }

    private function storePublicUpload(\Illuminate\Http\UploadedFile $file, string $dir): ?string
    {
        if (! $file->isValid()) {
            return null;
        }

        try {
            return $file->store($dir, 'public');
        } catch (\Throwable $e) {
            // Some environments miss `fileinfo`, causing MIME guessing / extension detection to fail.
            // Fall back to the client-provided extension (or none) so uploads still work.
            $ext = preg_replace('/[^a-z0-9]+/i', '', (string) $file->getClientOriginalExtension());
            $filename = Str::random(40) . ($ext !== '' ? ('.' . strtolower($ext)) : '');

            return $file->storeAs($dir, $filename, 'public');
        }
    }

    private function uploadedImageMeetsMinimumSize(\Illuminate\Http\UploadedFile $file, int $minWidth = 1024, int $minHeight = 714): bool
    {
        $mime = strtolower(trim((string) ($file->getMimeType() ?? '')));
        if ($mime !== '' && ! str_starts_with($mime, 'image/')) {
            return true;
        }

        try {
            $imageInfo = @getimagesize($file->getRealPath());
        } catch (\Throwable $e) {
            $imageInfo = false;
        }

        if (! is_array($imageInfo)) {
            return false;
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        return $width >= $minWidth && $height >= $minHeight;
    }

    /**
     * @return array<int, string>
     */
    private function sanitizeContractorServiceAreas(string $raw, string $address = '', string $zip = ''): array
    {
        $parts = collect(preg_split('/\s*,\s*/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $normalizedAddress = strtolower(trim($address));
        $normalizedZip = preg_replace('/\D+/', '', $zip) ?: '';

        return $parts
            ->reject(function (string $value) use ($normalizedAddress, $normalizedZip): bool {
                $normalizedValue = strtolower(trim($value));
                if ($normalizedValue === '') {
                    return true;
                }

                if ($normalizedAddress !== '' && $normalizedValue === $normalizedAddress) {
                    return true;
                }

                $digitsOnly = preg_replace('/\D+/', '', $value) ?: '';
                if ($normalizedZip !== '' && $digitsOnly !== '' && $digitsOnly === $normalizedZip) {
                    return true;
                }

                return false;
            })
            ->unique(fn (string $value) => strtolower($value))
            ->values()
            ->all();
    }

    public function property(Request $request): RedirectResponse
    {
        if (! auth()->check()) {
            return redirect('/signin');
        }

        $payload = $this->decodePayload($request);
        $status = $this->resolveStatus($request);
        $editListing = $this->resolveEditableListing($request, 'real-estate');
        $existingWizardData = is_array($editListing?->propertyDetail?->wizard_data)
            ? $editListing->propertyDetail->wizard_data
            : [];
        $wizardData = array_merge($existingWizardData, $payload);
        $wizardSession = trim((string) ($wizardData['wizard_session'] ?? ''));
        $freshStart = $request->boolean('fresh_start');
        $forceNewDraft = $freshStart && ! $request->filled('listing_id');
        $nextPath = trim((string) $request->input('next', ''));

        if ($validationRedirect = $this->validatePropertyWizardFlow(
            $wizardData,
            $status,
            $nextPath,
            $editListing?->id
        )) {
            return $validationRedirect;
        }

        if ($freshStart && auth()->check() && $wizardSession !== '') {
            $sessionHash = md5($wizardSession);
            Cache::forget('property-wizard-payload:' . auth()->id() . ':' . $sessionHash);
            Cache::forget('property-wizard-map:' . auth()->id() . ':' . $sessionHash);
            Cache::forget('property-draft-inflight:' . auth()->id() . ':' . $sessionHash);
            $editListing = null;
            $existingWizardData = [];
            $wizardData = $payload;
        }

        // Before the location step creates the listing id, keep step-1 data in cache keyed by wizard_session.
        // This prevents redirect loops like `?error=missing-state` when moving from the type step to location.
        if (! $forceNewDraft && auth()->check() && $wizardSession !== '') {
            $wizardCacheKey = 'property-wizard-payload:' . auth()->id() . ':' . md5($wizardSession);
            $cachedWizardData = Cache::get($wizardCacheKey);
            if (is_array($cachedWizardData) && $cachedWizardData) {
                $wizardData = array_merge($cachedWizardData, $wizardData);
            }
        }
        $wizardSessionMapKey = null;
        if (! $forceNewDraft && auth()->check() && $wizardSession !== '') {
            $wizardSessionMapKey = 'property-wizard-map:' . auth()->id() . ':' . md5($wizardSession);
            if (! $editListing) {
                $mappedListingId = (int) Cache::get($wizardSessionMapKey, 0);
                if ($mappedListingId > 0) {
                    $mappedListing = Listing::query()
                        ->where('id', $mappedListingId)
                        ->where('user_id', auth()->id())
                        ->where('module', 'real-estate')
                        ->first();
                    if ($mappedListing) {
                        $editListing = $mappedListing;
                    }
                }
            }
        }
        $inFlightDraftLockKey = null;
        if (! $forceNewDraft && $status === 'draft' && ! $editListing && auth()->check() && $wizardSession !== '') {
            $inFlightDraftLockKey = 'property-draft-inflight:' . auth()->id() . ':' . md5($wizardSession);
            if (! Cache::add($inFlightDraftLockKey, 1, now()->addSeconds(20))) {
                // A near-simultaneous click can hit here before the first request finishes.
                // Prefer routing the user to the next wizard step if we already have a listing mapped.
                $nextPath = trim((string) $request->input('next', ''));
                $mappedListingId = $wizardSessionMapKey ? (int) Cache::get($wizardSessionMapKey, 0) : 0;
                if ($mappedListingId > 0 && $nextPath !== '' && str_starts_with($nextPath, '/add-property')) {
                    return redirect($nextPath . '?edit=' . $mappedListingId);
                }
                if ($mappedListingId > 0) {
                    return redirect('/add-property?edit=' . $mappedListingId);
                }

                // If the mapping key wasn't written yet, fall back to the wizard_session stored in property_details.
                // This prevents "first click stays on the same page, second click works" race conditions.
                $sessionDraft = Listing::query()
                    ->where('user_id', auth()->id())
                    ->where('module', 'real-estate')
                    ->where('status', 'draft')
                    ->whereHas('propertyDetail', function ($query) use ($wizardSession) {
                        $query->where('wizard_data->wizard_session', $wizardSession);
                    })
                    ->latest('id')
                    ->first();
                if ($sessionDraft) {
                    if ($nextPath !== '' && str_starts_with($nextPath, '/add-property')) {
                        return redirect($nextPath . '?edit=' . $sessionDraft->id);
                    }

                    return redirect('/add-property-location?edit=' . $sessionDraft->id);
                }

                // Last-chance: the first request may still be creating the draft listing.
                // Briefly wait for the mapping to appear, then route forward instead of "reloading" the same page.
                $deadline = microtime(true) + 1.2;
                while (microtime(true) < $deadline) {
                    usleep(150000);
                    $mappedListingId = $wizardSessionMapKey ? (int) Cache::get($wizardSessionMapKey, 0) : 0;
                    if ($mappedListingId > 0 && $nextPath !== '' && str_starts_with($nextPath, '/add-property')) {
                        return redirect($nextPath . '?edit=' . $mappedListingId);
                    }
                    if ($mappedListingId > 0) {
                        return redirect('/add-property?edit=' . $mappedListingId);
                    }

                    $sessionDraft = Listing::query()
                        ->where('user_id', auth()->id())
                        ->where('module', 'real-estate')
                        ->where('status', 'draft')
                        ->whereHas('propertyDetail', function ($query) use ($wizardSession) {
                            $query->where('wizard_data->wizard_session', $wizardSession);
                        })
                        ->latest('id')
                        ->first();
                    if ($sessionDraft) {
                        if ($nextPath !== '' && str_starts_with($nextPath, '/add-property')) {
                            return redirect($nextPath . '?edit=' . $sessionDraft->id);
                        }

                        return redirect('/add-property-location?edit=' . $sessionDraft->id);
                    }
                }

                // If we don't have a mapped listing yet, keep the user in the wizard instead of dumping them
                // to account listings. This is usually caused by duplicate event handlers / double submits.
                $referer = (string) ($request->headers->get('referer') ?? '');
                if ($referer !== '' && str_contains($referer, '/add-property')) {
                    return redirect()->back();
                }

                return redirect('/account/listings?saved=draft');
            }
        }
        if (! $forceNewDraft && ! $editListing && $wizardSession !== '' && auth()->check()) {
            $sessionListing = Listing::query()
                ->where('user_id', auth()->id())
                ->where('module', 'real-estate')
                ->whereHas('propertyDetail', function ($query) use ($wizardSession) {
                    $query->where('wizard_data->wizard_session', $wizardSession);
                })
                ->latest('id')
                ->first();
            if ($sessionListing) {
                $editListing = $sessionListing;
            }
        }

        $categorySlug = $this->mapPropertyCategorySlug((string) ($wizardData['radio:type'] ?? ''));
        $category = $this->resolveCategory('real-estate', $categorySlug);
        $rawState = $this->firstNonEmpty($wizardData, [
            'state',
            'select:state',
            'select:state-select',
            'state-select',
        ]);
        $stateCode = $this->normalizeUsStateCode($rawState);
        if ($stateCode === '' || ! $this->resolveUsState($stateCode)) {
            if ($status === 'draft' && $wizardSession !== '' && str_starts_with($nextPath, '/add-property-location')) {
                $wizardCacheKey = 'property-wizard-payload:' . auth()->id() . ':' . md5($wizardSession);
                Cache::put($wizardCacheKey, $wizardData, now()->addHours(6));
                return redirect('/add-property-location');
            }

            return redirect('/add-property-location?error=missing-state');
        }
        $city = $this->resolveCity($this->firstNonEmpty($wizardData, [
            'select:city-select',
            'select:location-select',
            'select:city',
            'city-select',
            'city',
        ]), $stateCode);
        if (! $city && $editListing?->city) {
            $city = $editListing->city;
        }

        if (! $category || ! $city) {
            return redirect('/add-property?error=missing-taxonomy');
        }

        $title = trim(implode(' ', array_filter([
            (string) ($wizardData['radio:type'] ?? ''),
            (string) ($wizardData['address'] ?? ''),
            (string) ($wizardData['zip'] ?? ''),
        ])));

        if ($title === '' && $editListing) {
            $title = (string) $editListing->title;
        }
        if ($title === '') {
            $title = 'Property Listing';
        }

        $draftLockKey = null;
        if (! $forceNewDraft && $status === 'draft' && ! $editListing && $wizardSession === '' && auth()->check()) {
            $payloadHash = md5(json_encode($wizardData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            $draftLockKey = 'property-draft-lock:' . auth()->id() . ':' . $payloadHash;
            $lockedId = (int) Cache::get($draftLockKey, 0);
            if ($lockedId > 0) {
                $lockedListing = Listing::query()
                    ->where('id', $lockedId)
                    ->where('user_id', auth()->id())
                    ->where('module', 'real-estate')
                    ->first();
                if ($lockedListing) {
                    $editListing = $lockedListing;
                }
            }
        }

        $listingType = ((string) ($wizardData['radio:category'] ?? ($existingWizardData['radio:category'] ?? 'sell'))) === 'rent' ? 'rent' : 'sale';
        $priceRaw = (string) ($wizardData['price'] ?? ($existingWizardData['price'] ?? ''));
        $priceAmount = $this->normalizeInteger($priceRaw);
        $price = Listing::normalizePrice($priceRaw !== '' ? $priceRaw : null, $listingType === 'rent');
        $excerpt = $this->firstNonEmpty($wizardData, [
            'user-info',
            'description',
            'details',
        ]);
        if ($excerpt === '' && $editListing) {
            $excerpt = (string) ($editListing->excerpt ?? '');
        }

        $features = $this->resolvePropertyFeatures($wizardData, $editListing?->features ?? []);
        $listingData = [
            'category_id' => $category->id,
            'city_id' => $city->id,
            'module' => 'real-estate',
            'title' => $title,
            'excerpt' => $excerpt,
            'price' => $price,
            'budget_tier' => $this->resolveBudgetTier($priceRaw),
            'availability_now' => (bool) ($wizardData['tour'] ?? ($editListing?->availability_now ?? false)),
            'features' => $features,
            'rating' => 0,
            'reviews_count' => 0,
            'status' => $status,
            'published_at' => $status === 'published'
                ? ($editListing?->published_at ?: Carbon::now())
                : null,
        ];

        if (Schema::hasColumn('listings', 'price_amount')) {
            $listingData['price_amount'] = $priceAmount > 0 ? $priceAmount : null;
        }

        if ($editListing) {
            $listingData['slug'] = $this->makeUniqueSlug($title, 'property-listing', $editListing->id);
            $listingData['image'] = $editListing->image ?: '/finder/assets/img/listings/real-estate/03.jpg';
            $editListing->update($listingData);
            $listing = $editListing->fresh(['city']);
        } else {
            $recentDraft = null;
            if (! $forceNewDraft && $status === 'draft' && $wizardSession === '' && auth()->check()) {
                $recentDraft = Listing::query()
                    ->where('user_id', auth()->id())
                    ->where('module', 'real-estate')
                    ->where('status', 'draft')
                    ->where('title', $title)
                    ->where('created_at', '>=', Carbon::now()->subMinutes(5))
                    ->latest('id')
                    ->first();
            }

            if ($recentDraft) {
                $listingData['slug'] = $recentDraft->slug ?: $this->makeUniqueSlug($title, 'property-listing', $recentDraft->id);
                $listingData['user_id'] = auth()->id();
                $listingData['image'] = $recentDraft->image ?: '/finder/assets/img/listings/real-estate/03.jpg';
                $recentDraft->update($listingData);
                $listing = $recentDraft->fresh(['city']);
            } else {
                $listingData['slug'] = $this->makeUniqueSlug($title, 'property-listing');
                $listingData['user_id'] = auth()->id();
                $listingData['image'] = '/finder/assets/img/listings/real-estate/03.jpg';
                $listing = Listing::query()->create($listingData);
            }
        }

        $propertyDetailPayload = [
            'property_type' => $this->mapPropertyType((string) ($wizardData['radio:type'] ?? '')),
            'bedrooms' => $this->extractCount((string) ($wizardData['radio:bedrooms'] ?? '')),
            'bathrooms' => $this->extractCount((string) ($wizardData['radio:bathrooms'] ?? '')),
            'area_sqft' => $this->normalizeInteger((string) ($wizardData['total-area'] ?? '')),
            'listing_type' => $listingType,
        ];
        if (Schema::hasColumn('property_details', 'wizard_data')) {
            $propertyDetailPayload['wizard_data'] = $wizardData;
        }

        PropertyDetail::query()->updateOrCreate(
            ['listing_id' => $listing->id],
            $propertyDetailPayload
        );

        if ($request->hasFile('photos')) {
            $files = $request->file('photos');
            if (! is_array($files)) {
                $files = [$files];
            }
            $stored = [];
            foreach ($files as $idx => $file) {
                if (! $file) {
                    continue;
                }
                if (! ($file instanceof \Illuminate\Http\UploadedFile)) {
                    continue;
                }
                if (! $this->uploadedImageMeetsMinimumSize($file)) {
                    return redirect('/add-property-photos?error=image-too-small&edit=' . $listing->id);
                }
                $path = $this->storePublicUpload($file, 'listings/properties');
                if (! $path) {
                    continue;
                }
                
                $stored[] = $path;
                ListingImage::query()->create([
                    'listing_id' => $listing->id,
                    'image_path' => $path,
                    'sort_order' => (int) $idx,
                    'is_cover' => $idx === 0,
                ]);
            }
            if (count($stored) > 0) {
                $listing->image = $stored[0];
                $listing->save();
            }
        }

        if ($draftLockKey && $status === 'draft') {
            Cache::put($draftLockKey, $listing->id, now()->addMinutes(2));
        }
        if ($wizardSessionMapKey) {
            Cache::put($wizardSessionMapKey, $listing->id, now()->addHours(12));
        }
        if ($inFlightDraftLockKey) {
            Cache::forget($inFlightDraftLockKey);
        }

        $this->syncSubscriptionOrder($listing);

        if ($status === 'draft' && $nextPath !== '' && str_starts_with($nextPath, '/add-property')) {
            return redirect($nextPath . '?edit=' . $listing->id);
        }

        if ($status === 'published' && ! empty($listing->slug)) {
            return redirect('/entry/real-estate?created=1&slug=' . urlencode((string) $listing->slug));
        }

        return $this->redirectAfterSubmission(
            $status,
            '/account/listings',
            '/account/listings',
            $title,
            $listing->id
        );
    }

    public function contractor(Request $request): RedirectResponse
    {
        if (! auth()->check()) {
            return redirect('/signin');
        }

        $payload = $this->decodePayload($request);
        $status = $this->resolveStatus($request);
        $nextPath = trim((string) $request->input('next', ''));
        $editListing = $this->resolveEditableListing($request, 'contractors');

        if ($validationRedirect = $this->validateContractorWizardFlow(
            $payload,
            $status,
            $nextPath,
            $editListing,
            $request
        )) {
            return $validationRedirect;
        }

        $selectedCategory = $this->firstNonEmpty($payload, [
            'select:select-categories',
            'select:project-type',
            'project-type',
            'radio:project-type',
            'category',
        ]);
        if ($selectedCategory === '' && $editListing?->category) {
            $selectedCategory = (string) ($editListing->category->name ?: $editListing->category->slug);
        }
        $category = $this->resolveCategory('contractors', $selectedCategory !== '' ? $selectedCategory : 'Remodeling');
        $stateCode = $this->normalizeUsStateCode((string) ($payload['state'] ?? ''));
        $hasValidState = $stateCode !== '' && $this->resolveUsState($stateCode);
        $city = null;

        if ($hasValidState) {
            $city = $this->resolveCity($this->firstNonEmpty($payload, [
                'select:city-select',
                'select:location-select',
                'select:city',
                'city-select',
                'city',
            ]), $stateCode);
        }

        // Edit-mode tolerance: if location fields are missing on the final step, keep the existing city.
        if (! $city && $editListing?->city) {
            $city = $editListing->city;
        }

        if (! $hasValidState && ! $city) {
            return redirect('/add-contractor-location?error=missing-state');
        }

        if (! $category || ! $city) {
            return redirect('/add-contractor?error=missing-taxonomy');
        }

        $projectName = trim((string) ($payload['project-name'] ?? ''));
        if ($projectName === '' && $editListing) {
            $projectName = trim((string) $editListing->title);
        }
        $title = $projectName !== '' ? $projectName : ucfirst(str_replace('-', ' ', $category->slug)) . ' Service';
        $priceRaw = trim((string) ($payload['price'] ?? ''));
        if ($priceRaw === '' && $editListing) {
            $priceRaw = preg_replace('/[^\d]/', '', (string) ($editListing->price ?? '')) ?: '';
        }
        $priceAmount = $this->normalizeInteger($priceRaw);
        $existingFeatures = is_array($editListing?->features) ? $editListing->features : [];
        $existingPromotionTokens = collect($existingFeatures)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => str_starts_with($value, 'promo-package:') || str_starts_with($value, 'promo-service:'))
            ->values()
            ->all();
        $baseFeatures = collect($existingFeatures)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->reject(fn ($value) => str_starts_with($value, 'promo-package:') || str_starts_with($value, 'promo-service:'))
            ->values()
            ->all();

        $features = in_array('verified-hires', $baseFeatures, true) ? $baseFeatures : array_merge($baseFeatures, ['verified-hires']);

        $hasHomeEstimateInputs = array_key_exists('home-renovations', $payload) || array_key_exists('custom-home-building', $payload);
        if ($hasHomeEstimateInputs) {
            $features = array_values(array_filter($features, fn ($value) => $value !== 'free-estimate'));
            if (($payload['home-renovations'] ?? false) || ($payload['custom-home-building'] ?? false)) {
                $features[] = 'free-estimate';
            }
        }

        $hasConsultationInputs = array_key_exists('architectural-design', $payload) || array_key_exists('bathroom-design', $payload);
        if ($hasConsultationInputs) {
            $features = array_values(array_filter($features, fn ($value) => $value !== 'free-consultation'));
            if (($payload['architectural-design'] ?? false) || ($payload['bathroom-design'] ?? false)) {
                $features[] = 'free-consultation';
            }
        }

        $hasWeekendInputs = array_key_exists('monday', $payload) || array_key_exists('saturday', $payload) || array_key_exists('sunday', $payload);
        if ($hasWeekendInputs) {
            $features = array_values(array_filter($features, fn ($value) => $value !== 'weekend-consultations'));
            if (($payload['monday'] ?? false) && ($payload['saturday'] ?? false || $payload['sunday'] ?? false)) {
                $features[] = 'weekend-consultations';
            }
        }

        $servicesRaw = $payload['services'] ?? [];
        if (is_string($servicesRaw)) {
            $servicesRaw = [$servicesRaw];
        }
        $serviceTokens = is_array($servicesRaw)
            ? collect($servicesRaw)
                ->map(fn ($v) => trim((string) $v))
                ->filter()
                ->map(fn ($v) => 'service:' . $v)
                ->values()
            ->all()
            : [];
        if ($serviceTokens !== []) {
            $features = array_values(array_filter($features, fn ($value) => ! str_starts_with($value, 'service:')));
            $features = array_values(array_unique(array_merge($features, $serviceTokens)));
        }

        $promotionPackage = strtolower(trim((string) ($payload['promotion_package'] ?? $payload['package'] ?? '')));
        $promotionServices = [
            'certify' => ! empty($payload['service_certify']),
            'lifts' => ! empty($payload['service_lifts']),
            'analytics' => ! empty($payload['service_analytics']),
        ];
        $hasPromotionPayload = $promotionPackage !== ''
            || array_key_exists('service_certify', $payload)
            || array_key_exists('service_lifts', $payload)
            || array_key_exists('service_analytics', $payload);

        if ($hasPromotionPayload) {
            if ($promotionPackage !== '') {
                $features[] = 'promo-package:' . $promotionPackage;
            }

            foreach ($promotionServices as $serviceKey => $enabled) {
                if ($enabled) {
                    $features[] = 'promo-service:' . $serviceKey;
                }
            }
        } else {
            $features = array_merge($features, $existingPromotionTokens);
        }

        $features = array_values(array_unique($features));

        $listingData = [
            'category_id' => $category->id,
            'city_id' => $city->id,
            'module' => 'contractors',
            'title' => $title,
            'excerpt' => $this->firstNonEmpty($payload, [
                'project-description',
                'user-info',
                'description',
            ]) ?: (string) ($editListing?->excerpt ?? ''),
            'price' => Listing::normalizePrice($priceRaw !== '' ? "From {$priceRaw}" : null),
            'budget_tier' => $this->resolveBudgetTier($priceRaw),
            'availability_now' => true,
            'features' => array_values(array_unique($features)),
            'rating' => 0,
            'reviews_count' => 0,
            'status' => $status,
            'published_at' => $status === 'published' ? Carbon::now() : null,
        ];

        if (Schema::hasColumn('listings', 'price_amount')) {
            $listingData['price_amount'] = $priceAmount > 0 ? $priceAmount : null;
        }

        if ($editListing) {
            $listingData['slug'] = $this->makeUniqueSlug($title, 'contractor-service', $editListing->id);
            $editListing->update($listingData);
            $listing = $editListing->fresh(['city', 'images']);
        } else {
            $listingData['slug'] = $this->makeUniqueSlug($title, 'contractor-service');
            $listingData['user_id'] = auth()->id();
            $listing = Listing::query()->create($listingData)->fresh(['city', 'images']);
        }

        $existingContractorAddress = Schema::hasColumn('contractor_details', 'address_line')
            ? trim((string) ($editListing?->contractorDetail?->address_line ?? ''))
            : '';
        $existingContractorZip = Schema::hasColumn('contractor_details', 'zip_code')
            ? trim((string) ($editListing?->contractorDetail?->zip_code ?? ''))
            : '';
        $existingContractorState = Schema::hasColumn('contractor_details', 'state_code')
            ? trim((string) ($editListing?->contractorDetail?->state_code ?? ''))
            : '';

        $addressLine = trim((string) ($payload['address'] ?? ''));
        if ($addressLine === '') {
            $addressLine = $existingContractorAddress;
        }

        $zipCode = trim((string) ($payload['zip'] ?? ''));
        if ($zipCode === '') {
            $zipCode = $existingContractorZip;
        }

        $effectiveStateCode = $stateCode !== '' ? $stateCode : $existingContractorState;
        if ($effectiveStateCode === '' && $city && Schema::hasColumn('cities', 'state_code')) {
            $effectiveStateCode = trim((string) ($city->state_code ?? ''));
        }

        $features = array_values(array_filter($features, function ($value) {
            $token = strtolower(trim((string) $value));
            return ! str_starts_with($token, 'contractor-address:')
                && ! str_starts_with($token, 'contractor-zip:')
                && ! str_starts_with($token, 'contractor-state:');
        }));
        if ($addressLine !== '') {
            $features[] = 'contractor-address:' . $addressLine;
        }
        if ($zipCode !== '') {
            $features[] = 'contractor-zip:' . $zipCode;
        }
        if ($effectiveStateCode !== '') {
            $features[] = 'contractor-state:' . $effectiveStateCode;
        }
        $features = array_values(array_unique($features));

        if ($listing->features !== $features) {
            $listing->update([
                'features' => $features,
            ]);
            $listing->refresh();
        }

        $serviceAreaValues = $this->sanitizeContractorServiceAreas(
            (string) ($payload['area-search'] ?? ''),
            $addressLine,
            $zipCode
        );
        if (count($serviceAreaValues) === 0 && $editListing?->contractorDetail?->service_area) {
            $serviceAreaValues = $this->sanitizeContractorServiceAreas(
                (string) $editListing->contractorDetail->service_area,
                $addressLine,
                $zipCode
            );
        }
        $serviceArea = trim(implode(', ', $serviceAreaValues));

        $existingHours = is_array($editListing?->contractorDetail?->business_hours)
            ? $editListing->contractorDetail->business_hours
            : [];
        $businessHours = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $existingDay = $existingHours[$day] ?? null;
            $existingEnabled = is_array($existingDay)
                ? (bool) ($existingDay['enabled'] ?? false)
                : (bool) $existingDay;
            $existingFrom = is_array($existingDay)
                ? trim((string) ($existingDay['from'] ?? ''))
                : '';
            $existingTo = is_array($existingDay)
                ? trim((string) ($existingDay['to'] ?? ''))
                : '';

            $enabled = array_key_exists($day, $payload)
                ? (bool) $payload[$day]
                : $existingEnabled;
            $from = trim((string) ($payload[$day . 'From'] ?? $existingFrom));
            $to = trim((string) ($payload[$day . 'To'] ?? $existingTo));

            $businessHours[$day] = [
                'enabled' => $enabled,
                'from' => $enabled ? $from : '',
                'to' => $enabled ? $to : '',
            ];
        }

        ContractorDetail::query()->updateOrCreate(
            ['listing_id' => $listing->id],
            [
                'service_area' => $serviceArea !== '' ? $serviceArea : ($city->name . ' Metro'),
                ...(
                    Schema::hasColumn('contractor_details', 'address_line')
                        ? ['address_line' => $addressLine !== '' ? $addressLine : null]
                        : []
                ),
                ...(
                    Schema::hasColumn('contractor_details', 'zip_code')
                        ? ['zip_code' => $zipCode !== '' ? $zipCode : null]
                        : []
                ),
                ...(
                    Schema::hasColumn('contractor_details', 'state_code')
                        ? ['state_code' => $effectiveStateCode !== '' ? $effectiveStateCode : null]
                        : []
                ),
                'license_number' => $this->firstNonEmpty($payload, ['license-number', 'license_number', 'license']) ?: null,
                'is_verified' => (bool) ($payload['is-verified'] ?? false),
                'business_hours' => $businessHours,
                ...(
                    Schema::hasColumn('contractor_details', 'profile_image_path')
                        ? ['profile_image_path' => $editListing?->contractorDetail?->profile_image_path]
                        : []
                ),
            ]
        );

        $listing->refresh();
        $existingProfilePath = trim((string) (
            (
                Schema::hasColumn('contractor_details', 'profile_image_path')
                    ? ($listing->contractorDetail?->profile_image_path ?? '')
                    : ''
            ) ?: ($listing->image ?? '')
        ));
        $profileImagePath = '';
        $galleryCoverPath = '';
        $profilePhoto = $request->file('profile_photo');
        if ($profilePhoto instanceof \Illuminate\Http\UploadedFile && $profilePhoto->isValid()) {
            if (! $this->uploadedImageMeetsMinimumSize($profilePhoto)) {
                return redirect('/add-contractor-profile?error=image-too-small&edit=' . $listing->id);
            }
            $profileImagePath = (string) ($this->storePublicUpload($profilePhoto, 'listings/contractors/profile') ?? '');
        }

        if ($request->hasFile('photos')) {
            $files = $request->file('photos');
            if (! is_array($files)) {
                $files = [$files];
            }

            $listing->images()->delete();
            $stored = [];
            $sort = 0;
            foreach ($files as $file) {
                if (! ($file instanceof \Illuminate\Http\UploadedFile) || ! $file->isValid()) {
                    continue;
                }
                if (! $this->uploadedImageMeetsMinimumSize($file)) {
                    return redirect('/add-contractor-project?error=image-too-small&edit=' . $listing->id);
                }
                $path = $this->storePublicUpload($file, 'listings/contractors/gallery');
                if (! $path) {
                    continue;
                }
                $stored[] = $path;
                ListingImage::query()->create([
                    'listing_id' => $listing->id,
                    'image_path' => $path,
                    'sort_order' => $sort,
                    'is_cover' => $sort === 0,
                ]);
                if ($sort === 0) {
                    $galleryCoverPath = $path;
                }
                $sort++;
            }
        }

        if ($profileImagePath !== '') {
            if (Schema::hasColumn('contractor_details', 'profile_image_path')) {
                $listing->contractorDetail()->updateOrCreate(
                    ['listing_id' => $listing->id],
                    ['profile_image_path' => $profileImagePath]
                );
            }
            $listing->image = $profileImagePath;
            $listing->save();
        } elseif ($existingProfilePath !== '') {
            $listing->image = $existingProfilePath;
            $listing->save();
        } elseif ($galleryCoverPath !== '') {
            $listing->image = $galleryCoverPath;
            $listing->save();
        }

        $this->syncSubscriptionOrder($listing);

        if ($status === 'draft' && $nextPath !== '' && (str_starts_with($nextPath, '/add-contractor') || str_starts_with($nextPath, '/account/payment'))) {
            $separator = str_contains($nextPath, '?') ? '&' : '?';
            return redirect($nextPath . $separator . 'edit=' . $listing->id);
        }

        if ($status === 'draft') {
            return redirect('/account/listings?saved=draft&edit=' . $listing->id);
        }

        if (! empty($listing->slug)) {
            return redirect('/entry/contractors?created=1&slug=' . urlencode((string) $listing->slug));
        }

        return redirect('/listings/contractors?created=1');
    }

    public function restaurant(Request $request): RedirectResponse
    {
        $status = $this->resolveStatus($request);
        $editListing = $this->resolveEditableListing($request, 'restaurants');
        $nextPath = trim((string) $request->input('next', ''));
        $requiresComplete = $status !== 'draft'
            || ($nextPath !== '' && str_starts_with($nextPath, '/add-restaurant-promotion'));
        $existingRestaurantMeta = [];
        $existingExcerptRaw = (string) ($editListing?->excerpt ?? '');
        if ($existingExcerptRaw !== '') {
            $decodedExistingMeta = json_decode($existingExcerptRaw, true);
            if (is_array($decodedExistingMeta) && ($decodedExistingMeta['_mc_restaurant_v1'] ?? false)) {
                $existingRestaurantMeta = $decodedExistingMeta;
            }
        }

        $servicesInput = $request->input('services', []);
        if (is_string($servicesInput)) {
            $servicesInput = [$servicesInput];
        }
        if ((!is_array($servicesInput) || !count(array_filter($servicesInput))) && !empty($existingRestaurantMeta['services']) && is_array($existingRestaurantMeta['services'])) {
            $servicesInput = $existingRestaurantMeta['services'];
        }

        $openingHoursInput = [];
        $openingHoursRaw = (string) $request->input('opening_hours', '');
        if ($openingHoursRaw !== '') {
            $decodedOpeningHours = json_decode($openingHoursRaw, true);
            if (is_array($decodedOpeningHours)) {
                $openingHoursInput = $decodedOpeningHours;
            }
        }
        if (!$openingHoursInput && !empty($existingRestaurantMeta['opening_hours']) && is_array($existingRestaurantMeta['opening_hours'])) {
            $openingHoursInput = $existingRestaurantMeta['opening_hours'];
        }

        if ($requiresComplete) {
            $hasExistingImages = false;
            if ($editListing) {
                $hasExistingImages = !empty($editListing->image)
                    || $editListing->images()->exists();
            }

            $hasUploadedImages = $request->hasFile('cover_photo')
                || $request->hasFile('gallery_photos');

            $enabledOpeningDays = collect($openingHoursInput)
                ->filter(fn ($row) => !empty($row['enabled']))
                ->keys()
                ->values()
                ->all();

            $hasInvalidEnabledHours = collect($openingHoursInput)
                ->filter(fn ($row) => !empty($row['enabled']))
                ->contains(function ($row) {
                    $from = trim((string) ($row['from'] ?? ''));
                    $to = trim((string) ($row['to'] ?? ''));
                    return $from === '' || $to === '';
                });

            $restaurantNameForValidation = trim((string) $request->input('restaurant_name', ''))
                ?: trim((string) ($editListing?->title ?? ''));
            $addressForValidation = trim((string) $request->input('address', ''))
                ?: trim((string) ($existingRestaurantMeta['address'] ?? ''));
            $stateForValidation = trim((string) $request->input('state', ''))
                ?: trim((string) ($existingRestaurantMeta['state'] ?? ''))
                ?: trim((string) ($editListing?->city?->state_code ?? ''));
            $cityForValidation = trim((string) $request->input('city', ''))
                ?: trim((string) ($editListing?->city?->name ?? ''));
            $contactNameForValidation = trim((string) $request->input('contact_name', ''))
                ?: trim((string) ($existingRestaurantMeta['contact_name'] ?? ''));
            $phoneForValidation = trim((string) $request->input('phone', ''))
                ?: trim((string) ($existingRestaurantMeta['phone'] ?? ''));

            $validator = Validator::make(
                array_merge($request->all(), [
                    'restaurant_name' => $restaurantNameForValidation,
                    'address' => $addressForValidation,
                    'state' => $stateForValidation,
                    'city' => $cityForValidation,
                    'contact_name' => $contactNameForValidation,
                    'phone' => $phoneForValidation,
                    '_services_count' => is_array($servicesInput) ? count(array_filter($servicesInput)) : 0,
                    '_enabled_hours_count' => count($enabledOpeningDays),
                    '_has_hours_gaps' => $hasInvalidEnabledHours ? '1' : '0',
                    '_has_image' => ($hasUploadedImages || $hasExistingImages) ? '1' : '',
                ]),
                [
                    'restaurant_name' => ['required', 'string', 'max:255'],
                    'address' => ['required', 'string', 'max:255'],
                    'state' => ['required', 'string'],
                    'city' => ['required', 'string'],
                    'contact_name' => ['required', 'string', 'max:255'],
                    'phone' => ['required', 'string', 'max:50'],
                    '_services_count' => ['required', 'integer', 'min:1'],
                    '_enabled_hours_count' => ['required', 'integer', 'min:1'],
                    '_has_hours_gaps' => ['in:0'],
                    '_has_image' => ['required'],
                ],
                [
                    'restaurant_name.required' => 'Restaurant name is required.',
                    'address.required' => 'Location is required.',
                    'state.required' => 'Location is required.',
                    'city.required' => 'Location is required.',
                    'contact_name.required' => 'Contact person is required.',
                    'phone.required' => 'Phone number is required.',
                    '_services_count.min' => 'At least one service is required.',
                    '_enabled_hours_count.min' => 'Working hours are required.',
                    '_has_hours_gaps.in' => 'Complete from and to time for each selected day.',
                    '_has_image.required' => 'At least one restaurant image is required.',
                ]
            );

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }
        }

        $title = trim((string) $request->input('restaurant_name', ''));
        if ($title === '' && $editListing) {
            $title = trim((string) $editListing->title);
        }
        if ($title === '') {
            $title = 'Restaurant Listing';
        }

        $cuisineType = trim((string) $request->input('cuisine_type', ''));
        if ($cuisineType === '' && $editListing?->category?->name) {
            $cuisineType = (string) $editListing->category->name;
        }
        $category = $this->resolveCategory('restaurants', $cuisineType);
        if (! $category && $editListing?->category_id) {
            $category = $editListing->category;
        }

        $stateCode = $this->normalizeUsStateCode((string) $request->input('state', ''));
        if ($stateCode === '' && !empty($existingRestaurantMeta['state'])) {
            $stateCode = $this->normalizeUsStateCode((string) $existingRestaurantMeta['state']);
        }
        if ($stateCode === '' && $editListing?->city && Schema::hasColumn('cities', 'state_code')) {
            $stateCode = (string) ($editListing->city->state_code ?? '');
        }
        if ($stateCode === '' || ! $this->resolveUsState($stateCode)) {
            return redirect('/add-restaurant?error=missing-state');
        }
        $cityRaw = trim((string) $request->input('city', ''));
        if ($cityRaw === '' && $editListing?->city?->name) {
            $cityRaw = (string) $editListing->city->name;
        }
        $city = $this->resolveCity($cityRaw, $stateCode);
        if (! $city && $editListing?->city) {
            $city = $editListing->city;
        }

        if (! $category || ! $city) {
            return redirect('/add-restaurant?error=missing-taxonomy');
        }

        $priceRange = trim((string) $request->input('price_range', ''));
        $rangeAmount = $priceRange !== '' ? $this->extractRestaurantRangeUpperBound($priceRange) : 0;
        $price = $priceRange !== '' ? $priceRange : null;
        $priceAmount = $rangeAmount > 0 ? $rangeAmount : null;
        $services = $servicesInput;

        $serviceAliases = [
            'dinein' => 'dine-in',
            'familyfriendly' => 'family-friendly',
            'outdoor' => 'outdoor-seating',
            'outdoor seating' => 'outdoor-seating',
        ];

        $serviceValues = collect($services)
            ->map(function ($value) use ($serviceAliases) {
                $raw = trim((string) $value);
                $normalized = strtolower($raw);
                return $serviceAliases[$normalized] ?? $raw;
            })
            ->filter()
            ->values()
            ->all();

        $existingFeatures = is_array($editListing?->features) ? $editListing->features : [];
        $existingPromotionTokens = collect($existingFeatures)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => str_starts_with($value, 'promo-package:') || str_starts_with($value, 'promo-service:'))
            ->values()
            ->all();
        $features = collect($existingFeatures)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->reject(fn ($value) => str_starts_with($value, 'promo-package:') || str_starts_with($value, 'promo-service:'))
            ->values()
            ->all();

        $serviceValues = array_values(array_unique($serviceValues));
        $openingHours = $openingHoursInput;

        $promotionPackage = strtolower(trim((string) $request->input('promotion_package', $request->input('package', ''))));
        $promotionServices = [
            'certify' => $request->boolean('service_certify'),
            'lifts' => $request->boolean('service_lifts'),
            'analytics' => $request->boolean('service_analytics'),
        ];
        $hasPromotionPayload = $promotionPackage !== ''
            || $request->exists('service_certify')
            || $request->exists('service_lifts')
            || $request->exists('service_analytics');

        if ($hasPromotionPayload) {
            if ($promotionPackage !== '') {
                $features[] = 'promo-package:' . $promotionPackage;
            }

            foreach ($promotionServices as $serviceKey => $enabled) {
                if ($enabled) {
                    $features[] = 'promo-service:' . $serviceKey;
                }
            }
        } else {
            $features = array_merge($features, $existingPromotionTokens);
        }

        $features = array_values(array_unique(array_filter(array_map(
            fn ($feature) => trim((string) $feature),
            $features
        ))));

        $restaurantMeta = [
            '_mc_restaurant_v1' => true,
            'address' => trim((string) $request->input('address', '')) ?: trim((string) ($existingRestaurantMeta['address'] ?? '')),
            'zip_code' => trim((string) $request->input('zip_code', '')) ?: trim((string) ($existingRestaurantMeta['zip_code'] ?? '')),
            'country' => 'United States',
            'state' => $stateCode,
            'seating_capacity' => trim((string) $request->input('seating_capacity', '')) ?: trim((string) ($existingRestaurantMeta['seating_capacity'] ?? '')),
            'services' => $serviceValues,
            'opening_hours' => $openingHours,
            'contact_name' => trim((string) $request->input('contact_name', '')) ?: trim((string) ($existingRestaurantMeta['contact_name'] ?? '')),
            'phone' => trim((string) $request->input('phone', '')) ?: trim((string) ($existingRestaurantMeta['phone'] ?? '')),
            'email' => trim((string) $request->input('email', '')) ?: trim((string) ($existingRestaurantMeta['email'] ?? '')),
        ];
        $restaurantMetaJson = json_encode($restaurantMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $listingData = [
            'category_id' => $category->id,
            'city_id' => $city->id,
            'module' => 'restaurants',
            'title' => $title,
            'excerpt' => $restaurantMetaJson ?: '',
            'price' => $price,
            'budget_tier' => $this->resolveRestaurantBudgetTier($priceRange),
            'availability_now' => true,
            'features' => array_values(array_unique($features)),
            'rating' => 0,
            'reviews_count' => 0,
            'status' => $status,
            'published_at' => $status === 'published' ? Carbon::now() : null,
        ];

        if (Schema::hasColumn('listings', 'price_amount')) {
            $listingData['price_amount'] = $priceAmount && $priceAmount > 0 ? $priceAmount : null;
        }

        $uploadedImagePath = null;
        if ($request->hasFile('cover_photo')) {
            $cover = $request->file('cover_photo');
            if ($cover instanceof \Illuminate\Http\UploadedFile && $cover->isValid()) {
                if (! $this->uploadedImageMeetsMinimumSize($cover)) {
                    return redirect('/add-restaurant?error=image-too-small' . ($editListing ? '&edit=' . $editListing->id : ''));
                }
                $uploadedImagePath = $this->storePublicUpload($cover, 'listings/restaurants');
            }
        }

        if ($editListing) {
            $listingData['slug'] = $this->makeUniqueSlug($title, 'restaurant-listing', $editListing->id);
            $listingData['image'] = $uploadedImagePath ?: ($editListing->image ?: '/finder/assets/img/placeholders/preview-square.svg');
            $editListing->update($listingData);
            $listing = $editListing->fresh(['city', 'images']);
        } else {
            $listingData['slug'] = $this->makeUniqueSlug($title, 'restaurant-listing');
            $listingData['user_id'] = auth()->id();
            $listingData['image'] = $uploadedImagePath ?: '/finder/assets/img/placeholders/preview-square.svg';
            $listing = Listing::query()->create($listingData)->fresh(['city', 'images']);
        }

        $galleryCover = null;
        if ($request->hasFile('gallery_photos')) {
            $files = $request->file('gallery_photos');
            if (! is_array($files)) {
                $files = [$files];
            }

            $hasExistingImages = $listing->images()->exists();
            $sort = (int) $listing->images()->max('sort_order');
            $sort = $sort > 0 ? $sort + 1 : 0;
            foreach ($files as $file) {
                if (! ($file instanceof \Illuminate\Http\UploadedFile) || ! $file->isValid()) {
                    continue;
                }
                if (! $this->uploadedImageMeetsMinimumSize($file)) {
                    return redirect('/add-restaurant?error=image-too-small' . ($editListing ? '&edit=' . $editListing->id : ''));
                }
                $path = $this->storePublicUpload($file, 'listings/restaurants/gallery');
                if (! $path) {
                    continue;
                }
                ListingImage::query()->create([
                    'listing_id' => $listing->id,
                    'image_path' => $path,
                    'sort_order' => $sort,
                    'is_cover' => $sort === 0 && ! $hasExistingImages,
                ]);
                if ($sort === 0) {
                    $galleryCover = $path;
                }
                $sort++;
            }
        }

        if ($galleryCover && ! $uploadedImagePath) {
            $listing->image = $galleryCover;
            $listing->save();
        }

        $this->syncSubscriptionOrder($listing);

        if ($status === 'draft') {
            if ($nextPath !== '' && str_starts_with($nextPath, '/add-restaurant-promotion')) {
                return redirect($nextPath . '?edit=' . $listing->id);
            }
        }

        if ($status !== 'draft' && !empty($listing->slug)) {
            return redirect('/entry/restaurants?created=1&slug=' . urlencode((string) $listing->slug));
        }

        return $this->redirectAfterSubmission(
            $status,
            '/account/listings',
            '/listings/restaurants',
            $listing->title,
            $listing->id
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        $encoded = trim((string) $request->input('payload', $request->query('payload', '')));
        if ($encoded === '') {
            return [];
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            return [];
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $wizardData
     * @param array<int, mixed> $existingFeatures
     * @return array<int, string>
     */
    private function resolvePropertyFeatures(array $wizardData, array $existingFeatures = []): array
    {
        $derived = [];

        if ($this->wizardTruthy($wizardData, ['negotiated', 'negotiable'])) {
            $derived[] = 'negotiable';
        }

        if ($this->wizardTruthy($wizardData, ['no-credit', 'no_credit', 'noCredit', 'no_credit_sale'])) {
            $derived[] = 'no-credit';
        }

        if ($this->wizardTruthy($wizardData, ['ready-agents', 'ready_agents', 'readyAgents', 'agent'])) {
            $derived[] = 'agent-friendly';
        }

        if ($this->wizardTruthy($wizardData, ['exchange', 'exchange_possible'])) {
            $derived[] = 'exchange';
        }

        $amenities = [
            'tv' => 'tv-set',
            'washing' => 'washing-machine',
            'kitchen' => 'kitchen',
            'ac' => 'air-conditioning',
            'workspace' => 'separate-workplace',
            'fridge' => 'refrigerator',
            'drying' => 'drying-machine',
            'closet' => 'closet',
            'patio' => 'patio',
            'fireplace' => 'fireplace',
            'shower' => 'shower-cabin',
            'whirlpool' => 'whirlpool',
            'cctv' => 'security-cameras',
            'balcony' => 'balcony',
            'bar' => 'bar',
        ];
        foreach ($amenities as $key => $token) {
            if ($this->wizardTruthy($wizardData, [$key])) {
                $derived[] = $token;
            }
        }

        return array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), $derived))));
    }

    /**
     * @param array<string, mixed> $wizardData
     * @param array<int, string> $keys
     */
    private function wizardTruthy(array $wizardData, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $wizardData)) {
                continue;
            }
            $value = $wizardData[$key];
            if (is_bool($value)) {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return ((float) $value) !== 0.0;
            }
            $str = strtolower(trim((string) $value));
            if ($str === '') {
                return false;
            }
            if (in_array($str, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($str, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function resolveStatus(Request $request): string
    {
        $draft = (string) ($request->input('draft', $request->query('draft', '')));
        if ($draft === '1') {
            return 'draft';
        }

        return 'published';
    }

    /**
     * @param array<string, mixed> $wizardData
     */
    private function validatePropertyWizardFlow(array $wizardData, string $status, string $nextPath, ?int $editId = null): ?RedirectResponse
    {
        $isPublishing = $status === 'published';
        $needsStepOne = $isPublishing || str_starts_with($nextPath, '/add-property-location');
        $needsLocation = $isPublishing || str_starts_with($nextPath, '/add-property-photos');
        $needsDetails = $isPublishing || str_starts_with($nextPath, '/add-property-price');
        $needsPrice = $isPublishing || str_starts_with($nextPath, '/add-property-contact-info');
        $needsContact = $isPublishing || str_starts_with($nextPath, '/add-property-promotion');

        if ($needsStepOne) {
            if (
                $this->firstNonEmpty($wizardData, ['radio:category']) === ''
                || $this->firstNonEmpty($wizardData, ['radio:type']) === ''
                || $this->firstNonEmpty($wizardData, ['radio:condition']) === ''
            ) {
                return $this->redirectPropertyWizardStep('/add-property', 'missing-basics', $editId);
            }
        }

        if ($needsLocation) {
            if (
                $this->firstNonEmpty($wizardData, ['state']) === ''
                || $this->firstNonEmpty($wizardData, ['select:city-select', 'city']) === ''
                || $this->firstNonEmpty($wizardData, ['zip']) === ''
                || $this->firstNonEmpty($wizardData, ['address']) === ''
            ) {
                return $this->redirectPropertyWizardStep('/add-property-location', 'missing-location', $editId);
            }
        }

        if ($needsPrice) {
            if (
                $this->firstNonEmpty($wizardData, ['price']) === ''
                || $this->firstNonEmpty($wizardData, ['offer_type']) === ''
            ) {
                return $this->redirectPropertyWizardStep('/add-property-price', 'missing-price', $editId);
            }
        }

        if ($needsContact) {
            if (
                $this->firstNonEmpty($wizardData, ['fn']) === ''
                || $this->firstNonEmpty($wizardData, ['ln']) === ''
                || $this->firstNonEmpty($wizardData, ['email']) === ''
                || $this->firstNonEmpty($wizardData, ['phone']) === ''
            ) {
                return $this->redirectPropertyWizardStep('/add-property-contact-info', 'missing-contact', $editId);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $wizardData
     */
    private function validateContractorWizardFlow(array $wizardData, string $status, string $nextPath, ?Listing $editListing = null, ?Request $request = null): ?RedirectResponse
    {
        $editId = $editListing?->id;
        $isPublishing = $status === 'published';
        $needsLocation = $isPublishing || str_starts_with($nextPath, '/add-contractor-services');
        $needsServices = $isPublishing || str_starts_with($nextPath, '/add-contractor-profile');
        $needsProfile = $isPublishing || str_starts_with($nextPath, '/add-contractor-price-hours');
        $needsPriceHours = $isPublishing || str_starts_with($nextPath, '/add-contractor-project');
        $needsProject = $isPublishing || str_starts_with($nextPath, '/add-contractor-promotion');

        $existingFeatures = collect(is_array($editListing?->features) ? $editListing->features : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter();
        $existingServices = $existingFeatures
            ->filter(fn ($value) => str_starts_with($value, 'service:'))
            ->map(fn ($value) => trim(substr($value, strlen('service:'))))
            ->filter()
            ->values()
            ->all();
        $existingState = '';
        if ($editListing?->contractorDetail && Schema::hasColumn('contractor_details', 'state_code')) {
            $existingState = trim((string) ($editListing->contractorDetail->state_code ?? ''));
        }
        if ($existingState === '' && $editListing?->city && Schema::hasColumn('cities', 'state_code')) {
            $existingState = trim((string) ($editListing->city->state_code ?? ''));
        }
        $existingAddress = $editListing?->contractorDetail && Schema::hasColumn('contractor_details', 'address_line')
            ? trim((string) ($editListing->contractorDetail->address_line ?? ''))
            : '';
        $existingZip = $editListing?->contractorDetail && Schema::hasColumn('contractor_details', 'zip_code')
            ? trim((string) ($editListing->contractorDetail->zip_code ?? ''))
            : '';
        $existingServiceArea = $editListing?->contractorDetail
            ? trim((string) ($editListing->contractorDetail->service_area ?? ''))
            : '';
        $existingCity = trim((string) ($editListing?->city?->name ?? ''));
        $existingCategory = trim((string) ($editListing?->category?->name ?? $editListing?->category?->slug ?? ''));
        $existingProjectName = trim((string) ($editListing?->title ?? ''));
        $existingProjectDescription = trim((string) ($editListing?->excerpt ?? ''));
        $existingPrice = trim((string) (preg_replace('/[^\d]/', '', (string) ($editListing?->price ?? '')) ?: ''));

        if ($needsLocation) {
            $serviceAreas = $this->sanitizeContractorServiceAreas(
                $this->firstNonEmpty($wizardData, ['area-search']) !== ''
                    ? $this->firstNonEmpty($wizardData, ['area-search'])
                    : $existingServiceArea,
                $this->firstNonEmpty($wizardData, ['address']) !== ''
                    ? $this->firstNonEmpty($wizardData, ['address'])
                    : $existingAddress,
                $this->firstNonEmpty($wizardData, ['zip']) !== ''
                    ? $this->firstNonEmpty($wizardData, ['zip'])
                    : $existingZip
            );

            if (
                ($this->firstNonEmpty($wizardData, ['state']) === '' && $existingState === '')
                || ($this->firstNonEmpty($wizardData, ['select:city-select', 'select:city-value', 'city']) === '' && $existingCity === '')
                || ($this->firstNonEmpty($wizardData, ['zip']) === '' && $existingZip === '')
                || ($this->firstNonEmpty($wizardData, ['address']) === '' && $existingAddress === '')
                || $serviceAreas === []
            ) {
                return $this->redirectContractorWizardStep('/add-contractor-location', 'missing-location', $editId);
            }
        }

        if ($needsServices) {
            $selectedCategory = $this->firstNonEmpty($wizardData, [
                'select:select-categories',
                'select:project-type',
                'project-type',
                'radio:project-type',
                'category',
            ]);
            $services = $wizardData['services'] ?? [];
            if (is_string($services)) {
                $services = [$services];
            }
            $services = is_array($services)
                ? collect($services)->map(fn ($value) => trim((string) $value))->filter()->values()->all()
                : [];

            if ($selectedCategory === '') {
                $selectedCategory = $existingCategory;
            }
            if ($services === []) {
                $services = $existingServices;
            }

            if ($selectedCategory === '' || $services === []) {
                return $this->redirectContractorWizardStep('/add-contractor-services', 'missing-services', $editId);
            }
        }

        if ($needsProfile) {
            $about = $this->firstNonEmpty($wizardData, ['about', 'description']);
            if ($about === '' && $editListing) {
                $about = trim((string) ($editListing->description ?: $editListing->excerpt));
            }
            if (mb_strlen($about) < 30) {
                return $this->redirectContractorWizardStep('/add-contractor-profile', 'missing-profile', $editId);
            }
        }
        if ($needsPriceHours) {
            $price = $this->firstNonEmpty($wizardData, ['price']);
            if ($price === '') {
                $price = $existingPrice;
            }
            $hasValidHours = true;
            $hasEnabledHours = false;
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                $enabled = filter_var($wizardData[$day] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($enabled !== true) {
                    continue;
                }
                $hasEnabledHours = true;

                $from = $this->firstNonEmpty($wizardData, [$day . 'From']);
                $to = $this->firstNonEmpty($wizardData, [$day . 'To']);
                if ($from === '' || $to === '') {
                    $hasValidHours = false;
                    break;
                }
            }
            if (! $hasEnabledHours && $editListing?->contractorDetail && Schema::hasColumn('contractor_details', 'business_hours')) {
                $businessHours = $editListing->contractorDetail->business_hours;
                if (is_array($businessHours)) {
                    $hasValidHours = true;
                    foreach ($businessHours as $row) {
                        if (! is_array($row) || empty($row['enabled'])) {
                            continue;
                        }
                        $hasEnabledHours = true;
                        if (trim((string) ($row['from'] ?? '')) === '' || trim((string) ($row['to'] ?? '')) === '') {
                            $hasValidHours = false;
                            break;
                        }
                    }
                }
            }

            if ($price === '' || ! $hasEnabledHours || ! $hasValidHours) {
                return $this->redirectContractorWizardStep('/add-contractor-price-hours', 'missing-price-hours', $editId);
            }
        }

        if ($needsProject) {
            $projectName = $this->firstNonEmpty($wizardData, ['project-name']);
            $projectDescription = $this->firstNonEmpty($wizardData, ['project-description']);
            $projectPrice = $this->firstNonEmpty($wizardData, ['price']);
            if ($projectName === '') {
                $projectName = $existingProjectName;
            }
            if ($projectDescription === '') {
                $projectDescription = $existingProjectDescription;
            }
            if ($projectPrice === '') {
                $projectPrice = $existingPrice;
            }

            $hasProjectMedia = ($request?->hasFile('photos') ?? false)
                || ($editListing && $editListing->images()->exists());

            if ($projectName === '' || $projectDescription === '' || $projectPrice === '' || ! $hasProjectMedia) {
                return $this->redirectContractorWizardStep('/add-contractor-project', 'missing-project', $editId);
            }
        }

        return null;
    }

    private function redirectPropertyWizardStep(string $path, string $error, ?int $editId = null): RedirectResponse
    {
        $query = ['error' => $error];
        if ($editId) {
            $query['edit'] = $editId;
        }

        return redirect($path . '?' . http_build_query($query));
    }

    private function redirectContractorWizardStep(string $path, string $error, ?int $editId = null): RedirectResponse
    {
        $query = ['error' => $error];
        if ($editId) {
            $query['edit'] = $editId;
        }

        return redirect($path . '?' . http_build_query($query));
    }

    private function resolveEditableListing(Request $request, string $module): ?Listing
    {
        $listingId = (int) $request->input('listing_id', $request->query('listing_id', 0));
        if ($listingId <= 0) {
            return null;
        }

        return Listing::query()
            ->where('id', $listingId)
            ->where('module', $module)
            ->where('user_id', auth()->id())
            ->first();
    }

    private function resolveCategory(string $module, string $rawValue): ?Category
    {
        $slug = Str::slug($rawValue);
        $name = trim((string) $rawValue);

        $query = Category::query()->where('module', $module);

        if ($slug !== '') {
            $category = (clone $query)->where('slug', $slug)->first();
            if ($category) {
                return $category;
            }

            $category = (clone $query)->where('name', 'like', '%' . $rawValue . '%')->first();
            if ($category) {
                return $category;
            }

            return Category::query()->create([
                'module' => $module,
                'name' => $name !== '' ? $name : ucfirst(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'sort_order' => (int) Category::query()->where('module', $module)->max('sort_order') + 1,
                'is_active' => true,
            ]);
        }

        $fallback = (clone $query)->orderBy('sort_order')->first();
        if ($fallback) {
            return $fallback;
        }

        $generatedSlug = $slug !== '' ? $slug : Str::slug($module . '-general');
        $generatedName = $name !== '' ? $name : ucfirst(str_replace('-', ' ', $module)) . ' General';

        return Category::query()->create([
            'module' => $module,
            'name' => $generatedName,
            'slug' => $generatedSlug,
            'sort_order' => (int) Category::query()->where('module', $module)->max('sort_order') + 1,
            'is_active' => true,
        ]);
    }

    private function resolveUsState(string $stateCode): ?State
    {
        $stateCode = strtoupper(trim($stateCode));
        if (preg_match('/^[A-Z]{2}$/', $stateCode) !== 1) {
            return null;
        }

        if (!Schema::hasTable('states')) {
            return new State([
                'country_code' => 'US',
                'code' => $stateCode,
                'name' => $stateCode,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        $state = State::query()
            ->where('country_code', 'US')
            ->where('code', $stateCode)
            ->where('is_active', true)
            ->first();

        // Be tolerant when the `states` table exists but isn't seeded (common on fresh deployments).
        // Public listing forms should still work as long as the state code is valid.
        if (! $state) {
            return new State([
                'country_code' => 'US',
                'code' => $stateCode,
                'name' => $stateCode,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        return $state;
    }

    private function resolveCity(string $rawValue, string $stateCode = ''): ?City
    {
        $rawValue = trim($rawValue);
        $slug = Str::slug($rawValue);
        $stateCode = strtoupper(trim($stateCode));
        if ($stateCode !== '' && ! $this->resolveUsState($stateCode)) {
            return null;
        }

        if ($slug !== '') {
            $city = City::query()
                ->where('slug', $slug)
                ->when($stateCode !== '' && Schema::hasColumn('cities', 'state_code'), fn ($q) => $q->where('state_code', $stateCode))
                ->first();
            if ($city) {
                return $city;
            }

            $payload = [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'slug' => $slug,
            ];
            if (Schema::hasColumn('cities', 'state_code') && $stateCode !== '') {
                $payload['state_code'] = $stateCode;
            }
            if (Schema::hasColumn('cities', 'is_active')) {
                $payload['is_active'] = true;
            }
            if (Schema::hasColumn('cities', 'sort_order')) {
                $payload['sort_order'] = (int) City::query()->max('sort_order') + 1;
            }

            return City::query()->create($payload);
        }

        return City::query()->orderBy('sort_order')->first();
    }

    private function mapPropertyCategorySlug(string $type): string
    {
        return match (Str::slug($type)) {
            'apartment' => 'apartments',
            'house' => 'family-homes',
            'commercial' => 'commercial-spaces',
            'room' => 'townhouses',
            'garage' => 'condos',
            default => 'family-homes',
        };
    }

    private function mapPropertyType(string $type): string
    {
        return match (Str::slug($type)) {
            'commercial' => 'Commercial',
            default => 'Residential',
        };
    }

    private function mapContractorCategorySlug(?string $selectedCategory): string
    {
        return match (Str::slug((string) $selectedCategory)) {
            'roofing' => 'roofing',
            'plumbing' => 'plumbing',
            'electrician', 'electrical' => 'electrical',
            'painting', 'paiting' => 'painting',
            default => 'remodeling',
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $keys
     */
    private function firstNonEmpty(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_array($value)) {
                $value = $this->extractFirstValue($value);
            }
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveBudgetTier(string $rawAmount): int
    {
        $amount = $this->normalizeInteger($rawAmount);

        return match (true) {
            $amount <= 100 => 1,
            $amount <= 1000 => 2,
            $amount <= 5000 => 3,
            default => 4,
        };
    }

    private function resolveRestaurantBudgetTier(string $priceRange): int
    {
        $amount = $this->extractRestaurantRangeUpperBound($priceRange);

        return match (true) {
            $amount <= 50 => 1,
            $amount <= 100 => 2,
            $amount <= 500 => 3,
            $amount <= 1000 => 4,
            default => 5,
        };
    }

    private function extractRestaurantRangeUpperBound(string $value): int
    {
        preg_match_all('/\d+/', $value, $matches);
        $numbers = array_map('intval', $matches[0] ?? []);
        if (empty($numbers)) {
            return 0;
        }

        return (int) max($numbers);
    }

    private function normalizeInteger(string $value): int
    {
        return (int) preg_replace('/[^\d]/', '', $value);
    }

    private function extractCount(string $value): ?int
    {
        if ($value === '' || Str::endsWith($value, '-any')) {
            return null;
        }

        $count = $this->normalizeInteger($value);

        return $count > 0 ? $count : null;
    }

    /**
     * @param mixed $value
     */
    private function extractFirstValue($value): string
    {
        if (is_array($value)) {
            return trim((string) ($value[0] ?? ''));
        }

        return trim((string) $value);
    }

    private function makeUniqueSlug(string $title, string $fallback, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);
        if ($baseSlug === '') {
            $baseSlug = $fallback;
        }

        $slug = $baseSlug;
        $counter = 2;

        while (Listing::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function syncSubscriptionOrder(Listing $listing): void
    {
        app(SubscriptionSyncService::class)->syncForListing($listing);
    }

    private function redirectAfterSubmission(string $status, string $draftPath, string $publishedPath, string $title, ?int $listingId = null): RedirectResponse
    {
        if ($status === 'draft') {
            $qs = $listingId ? ('?saved=draft&edit=' . $listingId) : '?saved=draft';
            return redirect($draftPath . $qs);
        }

        if ($publishedPath === '/account/listings') {
            $qs = $listingId ? ('?created=1&published=' . $listingId) : '?created=1';
            return redirect($publishedPath . $qs);
        }

        return redirect($publishedPath . '?created=1&q=' . urlencode($title));
    }
}
