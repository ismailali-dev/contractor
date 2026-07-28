<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingSubmissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AccountBillingController;
use App\Http\Controllers\Api\PublicListingController;
use App\Http\Controllers\Api\ListingClaimController;
use App\Models\Listing;
use App\Models\ListingClaimRequest;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TaxonomyController;
use App\Http\Controllers\Admin\ExportController;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::middleware('auth')->group(function () use ($serve, $normalizeUsPhone) {
    Route::get('/account/profile', fn () => $serve('account-profile.html'));
    Route::get('/account/settings', fn () => $serve('account-settings.html'));
    Route::get('/account/listings', fn () => $serve('account-listings.html'));
    Route::get('/account/reviews', fn () => $serve('account-reviews.html'));
    Route::get('/account/favorites', fn () => $serve('account-favorites.html'));
    Route::get('/account/payment', fn () => $serve('account-payment.html'));
    Route::get('/account/subscriptions', fn () => $serve('account-subscriptions.html'));
    Route::get('/account/api/payment-methods', [AccountBillingController::class, 'paymentMethods']);
    Route::post('/account/api/payment-methods', [AccountBillingController::class, 'storePaymentMethod']);
    Route::match(['put', 'patch'], '/account/api/payment-methods/{paymentMethod}', [AccountBillingController::class, 'updatePaymentMethod']);
    Route::delete('/account/api/payment-methods/{paymentMethod}', [AccountBillingController::class, 'destroyPaymentMethod']);
    Route::get('/account/api/subscriptions', [AccountBillingController::class, 'subscriptions']);
    Route::get('/account/help-topics-v1.html', fn () => $serve('help-topics-v1.html'));
    Route::get('/account/help-topics-v2.html', fn () => $serve('help-topics-v2.html'));
    Route::get('/account/help-topics-v3.html', fn () => $serve('help-topics-v3.html'));
    Route::get('/account/help-single-article-v1.html', fn () => $serve('help-single-article-v1.html'));
    Route::get('/account/help-single-article-v2.html', fn () => $serve('help-single-article-v2.html'));
    Route::get('/account/help-single-article-v3.html', fn () => $serve('help-single-article-v3.html'));
    Route::get('/account/help-center', fn () => redirect('/account/help-topics-v1.html'));
    Route::get('/account/contractors/{listing}/edit-data', function (int $listing) {
        $editListing = Listing::query()
            ->with(['contractorDetail', 'city', 'images'])
            ->where('id', $listing)
            ->where('module', 'contractors')
            ->where('user_id', Auth::id())
            ->first();

        if (! $editListing) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $serviceArea = (string) ($editListing->contractorDetail?->service_area ?? '');
        $address = '';
        $zip = '';
        if ($serviceArea !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $serviceArea))));
            $zipCandidate = end($parts) ?: '';
            if ($zipCandidate !== '' && preg_match('/\d/', $zipCandidate)) {
                $zip = (string) $zipCandidate;
                array_pop($parts);
            }
            if (count($parts) > 0) {
                $address = (string) $parts[0];
            }
        }
        $serviceAreaParts = array_values(array_filter(array_map('trim', explode(',', $serviceArea))));
        $normalizedAddress = strtolower(trim($address));
        $normalizedZip = preg_replace('/\D+/', '', $zip) ?: '';
        $serviceArea = implode(', ', array_values(array_filter($serviceAreaParts, function ($value) use ($normalizedAddress, $normalizedZip) {
            $value = trim((string) $value);
            if ($value === '') {
                return false;
            }
            if ($normalizedAddress !== '' && strtolower($value) === $normalizedAddress) {
                return false;
            }
            $digitsOnly = preg_replace('/\D+/', '', $value) ?: '';
            if ($normalizedZip !== '' && $digitsOnly !== '' && $digitsOnly === $normalizedZip) {
                return false;
            }

            return true;
        })));

        $priceRaw = (string) ($editListing->price ?? '');
        $priceValue = preg_replace('/[^\d]/', '', $priceRaw) ?: '';

        $stateCode = '';
        if ($editListing->city && Schema::hasColumn('cities', 'state_code')) {
            $stateCode = (string) ($editListing->city->state_code ?? '');
        }

        $featureTokens = collect(is_array($editListing->features) ? $editListing->features : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter();
        $addressFallback = $featureTokens->first(fn ($token) => str_starts_with(strtolower($token), 'contractor-address:'));
        $zipFallback = $featureTokens->first(fn ($token) => str_starts_with(strtolower($token), 'contractor-zip:'));
        $stateFallback = $featureTokens->first(fn ($token) => str_starts_with(strtolower($token), 'contractor-state:'));
        $addressFallback = $addressFallback ? trim(substr($addressFallback, strlen('contractor-address:'))) : '';
        $zipFallback = $zipFallback ? trim(substr($zipFallback, strlen('contractor-zip:'))) : '';
        $stateFallback = $stateFallback ? trim(substr($stateFallback, strlen('contractor-state:'))) : '';
        $savedPackage = strtolower((string) ($featureTokens->first(fn ($token) => str_starts_with($token, 'promo-package:')) ?? ''));
        if ($savedPackage !== '') {
            $savedPackage = substr($savedPackage, strlen('promo-package:'));
        }

        return response()->json([
            'data' => [
                'id' => $editListing->id,
                'title' => (string) $editListing->title,
                'project_name' => (string) $editListing->title,
                'project_description' => (string) ($editListing->excerpt ?? ''),
                'category' => (string) ($editListing->category?->name ?? ''),
                'price_value' => (string) $priceValue,
                'city' => (string) ($editListing->city?->name ?? ''),
                'state' => (string) $stateCode,
                'service_area' => $serviceArea,
                'address' => (string) (
                    Schema::hasColumn('contractor_details', 'address_line') && !empty($editListing->contractorDetail?->address_line)
                        ? $editListing->contractorDetail->address_line
                        : ($addressFallback !== '' ? $addressFallback : $address)
                ),
                'zip' => (string) (
                    Schema::hasColumn('contractor_details', 'zip_code') && !empty($editListing->contractorDetail?->zip_code)
                        ? $editListing->contractorDetail->zip_code
                        : ($zipFallback !== '' ? $zipFallback : $zip)
                ),
                'services' => $featureTokens
                    ->filter(fn ($token) => str_starts_with($token, 'service:'))
                    ->map(fn ($token) => trim(substr($token, strlen('service:'))))
                    ->filter()
                    ->values()
                    ->all(),
                'business_hours' => is_array($editListing->contractorDetail?->business_hours)
                    ? $editListing->contractorDetail->business_hours
                    : [],
                'profile_image' => (string) (
                    Schema::hasColumn('contractor_details', 'profile_image_path') && !empty($editListing->contractorDetail?->profile_image_path)
                        ? asset('storage/' . ltrim((string) $editListing->contractorDetail->profile_image_path, '/'))
                        : ''
                ),
                'image' => (string) $editListing->image_url,
                'promotion_package' => $savedPackage,
                'service_certify' => $featureTokens->contains('promo-service:certify'),
                'service_lifts' => $featureTokens->contains('promo-service:lifts'),
                'service_analytics' => $featureTokens->contains('promo-service:analytics'),
                'gallery_images' => $editListing->images
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn ($img) => (string) $img->image_url)
                    ->all(),
            ],
        ]);
    });
    Route::post('/account/contractors/{listing}/profile-photo', function (Request $request, int $listing) {
        $record = Listing::query()
            ->where('id', $listing)
            ->where('module', 'contractors')
            ->where('user_id', Auth::id())
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate([
            'profile_photo' => ['required', 'image', 'max:8192', 'dimensions:min_width=1024,min_height=714'],
        ]);

        $file = $request->file('profile_photo');
        if (! ($file instanceof \Illuminate\Http\UploadedFile) || ! $file->isValid()) {
            return response()->json(['message' => 'Invalid upload'], 422);
        }

        $path = $file->store('listings/contractors/profile', 'public');
        $record->image = $path;
        $record->save();
        if (Schema::hasColumn('contractor_details', 'profile_image_path')) {
            $record->contractorDetail()->updateOrCreate(
                ['listing_id' => $record->id],
                ['profile_image_path' => $path]
            );
        }

        return response()->json([
            'ok' => true,
            'image' => $path,
            'image_url' => $record->fresh()->image_url,
        ]);
    });
    Route::post('/account/listings/delete', function (Request $request) {
        $listingId = (int) $request->input('listing_id', 0);
        if ($listingId <= 0) {
            return back();
        }

        $listing = Listing::query()
            ->with(['contractorDetail', 'propertyDetail', 'carDetail', 'eventDetail', 'images'])
            ->where('id', $listingId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $listing) {
            return back();
        }

        $listing->images()->delete();
        $listing->contractorDetail()->delete();
        $listing->propertyDetail()->delete();
        $listing->carDetail()->delete();
        $listing->eventDetail()->delete();
        $listing->delete();

        return redirect('/account/listings');
    });
    Route::post('/account/listings/publish', function (Request $request) {
        $listingId = (int) $request->input('listing_id', 0);
        if ($listingId <= 0) {
            return redirect('/account/listings');
        }

        $listing = Listing::query()
            ->where('id', $listingId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $listing) {
            return redirect('/account/listings');
        }

        $listing->status = 'published';
        if (! $listing->published_at) {
            $listing->published_at = now();
        }
        $listing->save();

        return redirect('/account/listings');
    });
    Route::post('/account/settings', function (Request $request) use ($normalizeUsPhone) {
        $formatPhoneForResponse = static function (?string $value): string {
            $raw = trim((string) $value);
            if ($raw === '') {
                return '';
            }

            $digits = preg_replace('/\D+/', '', $raw) ?? '';
            if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                $digits = substr($digits, 1);
            }

            if (strlen($digits) !== 10) {
                return $raw;
            }

            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
        };

        $hasLanguage = Schema::hasColumn('users', 'language');
        $normalizedEmail = strtolower(trim($request->string('email')->toString()));
        $request->merge(['email' => $normalizedEmail]);
        $validated = $request->validate([
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'string', 'max:100'],
            'language' => $hasLanguage ? ['nullable', 'string', 'max:100'] : ['nullable'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'max:4096'],
        ]);

        $user = Auth::user();
        if (! $user) {
            return redirect('/signin');
        }

        $emailExists = User::query()
            ->where('id', '!=', (int) $user->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->exists();

        if ($emailExists) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'This email is already registered to another account.']);
        }

        $normalizedPhone = ($validated['phone'] ?? null) ? $normalizeUsPhone($validated['phone']) : '';

        $user->first_name = trim((string) ($validated['first_name'] ?? ''));
        $user->last_name = trim((string) ($validated['last_name'] ?? ''));
        $user->company_name = trim((string) ($validated['company_name'] ?? ''));
        $user->name = trim(($user->first_name . ' ' . $user->last_name)) ?: ($user->name ?: 'User');
        if (($user->account_type ?? 'business') === 'business' && $user->company_name !== '') {
            $user->name = $user->company_name;
        }
        $user->email = strtolower((string) ($validated['email'] ?? $user->email));
        $user->phone = $normalizedPhone;
        $user->birth_date = trim((string) ($validated['birth_date'] ?? ''));
        if ($hasLanguage) {
            $user->language = trim((string) ($validated['language'] ?? ''));
        }
        $user->address = trim((string) ($validated['address'] ?? ''));
        $user->bio = trim((string) ($validated['bio'] ?? ''));

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')?->store('profiles', 'public');
            if ($path) {
                $user->avatar_path = $path;
            }
        }

        $user->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'profile' => [
                    'first_name' => (string) ($user->first_name ?? ''),
                    'last_name' => (string) ($user->last_name ?? ''),
                'name' => trim((string) ($user->name ?? '')) ?: 'User',
                'email' => (string) ($user->email ?? ''),
                'email_verified' => (bool) ($user->email_verified_at),
                'account_type' => (string) ($user->account_type ?? 'business'),
                'company_name' => (string) ($user->company_name ?? ''),
                'phone' => $formatPhoneForResponse($user->phone),
                'birth_date' => (string) ($user->birth_date ?? ''),
                'language' => $hasLanguage ? (string) ($user->language ?? '') : '',
                'address' => (string) ($user->address ?? ''),
                    'bio' => (string) ($user->bio ?? ''),
                    'avatar' => (string) ($user->avatar_url ?? ''),
                ],
            ]);
        }

        return redirect('/account/settings?settings=updated');
    });
    Route::post('/account/avatar', function (Request $request) {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:4096'],
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $path = $request->file('avatar')?->store('profiles', 'public');
        if (! $path) {
            return response()->json(['message' => 'Upload failed'], 422);
        }

        $user->avatar_path = $path;
        $user->save();

        return response()->json([
            'ok' => true,
            'avatar_url' => $user->avatar_url,
        ]);
    });
    Route::post('/account/password', function (Request $request) {
        $user = Auth::user();
        if (! $user) {
            return redirect('/signin');
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        if (! Hash::check((string) $validated['current_password'], (string) $user->password)) {
            return redirect('/account/settings?error=password');
        }

        $user->password = Hash::make((string) $validated['password']);
        $user->save();

        return redirect('/account/settings?password=updated');
    });
});

