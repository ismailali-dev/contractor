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

Route::get('/auth/status', function () {
    return response()->json([
        'authenticated' => Auth::check(),
        'user_id' => Auth::id(),
    ]);
});
$normalizeUsPhone = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) !== 10) {
        throw ValidationException::withMessages([
            'phone' => 'Enter a valid US phone number with 10 digits.',
        ]);
    }

    return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6, 4));
};
$formatUsPhoneForDisplay = static function (?string $value): string {
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
$safeAuthRedirect = static function (?string $target, string $fallback = '/account/profile'): string {
    $value = trim((string) $target);
    if ($value === '') {
        return $fallback;
    }

    if (! str_starts_with($value, '/')) {
        return $fallback;
    }

    if (str_starts_with($value, '//')) {
        return $fallback;
    }

    return $value;
};
Route::get('/signin', function () use ($serve, $safeAuthRedirect) {
    if (Auth::check()) {
        return redirect($safeAuthRedirect(request()->query('next')));
    }
    return $serve('account-signin.html');
});
Route::get('/signup', function () use ($serve, $safeAuthRedirect) {
    if (Auth::check()) {
        return redirect($safeAuthRedirect(request()->query('next')));
    }
    return $serve('account-signup.html');
});
Route::get('/password-recovery', function () use ($serve, $safeAuthRedirect) {
    if (Auth::check()) {
        return redirect($safeAuthRedirect(request()->query('next')));
    }
    return $serve('account-password-recovery.html');
});
Route::post('/signup', function (Request $request) use ($safeAuthRedirect, $normalizeUsPhone) {
    $email = strtolower(trim($request->string('email')->toString()));
    $request->merge(['email' => $email]);

    $data = $request->validate([
        'name' => ['nullable', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'account_type' => ['nullable', 'in:business,user'],
        'company_name' => ['nullable', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:50'],
        'next' => ['nullable', 'string', 'max:2048'],
    ]);

    // Prevent duplicate signups even if the database collation is case-sensitive.
    $exists = User::query()
        ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
        ->exists();

    if ($exists) {
        $query = http_build_query(array_filter([
            'error' => 'exists',
            'email' => $email,
            'account_type' => $data['account_type'] ?? null,
            'next' => ($data['next'] ?? null) ? $safeAuthRedirect($data['next'], '') : null,
        ]));

        return redirect('/signup?' . $query);
    }

    $name = ucfirst(str_replace(['.', '_', '-'], ' ', explode('@', $email)[0]));
    $accountType = (string) ($data['account_type'] ?? 'business');
    $next = $safeAuthRedirect($data['next'] ?? null, '');
    $companyName = trim((string) ($data['company_name'] ?? ''));
    $fullName = trim((string) ($data['name'] ?? ''));
    $normalizedPhone = $normalizeUsPhone($data['phone'] ?? '');
    if ($accountType === 'business' && $companyName === '') {
        return back()
            ->withInput()
            ->withErrors(['company_name' => 'Company name is required for business owner accounts.']);
    }
    if ($accountType === 'user' && $fullName === '') {
        return back()
            ->withInput()
            ->withErrors(['name' => 'Full name is required for user accounts.']);
    }
    if ($accountType === 'business' && $companyName !== '') {
        $name = $companyName;
    } elseif ($accountType === 'user' && $fullName !== '') {
        $name = $fullName;
    }

    User::create([
        'name' => $name ?: 'Monaclick User',
        'email' => $email,
        'account_type' => $accountType,
        'company_name' => $companyName !== '' ? $companyName : null,
        'phone' => $normalizedPhone,
        'password' => Hash::make($data['password']),
    ]);

    $query = http_build_query(array_filter([
        'created' => 1,
        'email' => $email,
        'account_type' => $accountType,
        'next' => $next !== '' ? $next : null,
    ]));

    return redirect('/signin' . ($query !== '' ? '?' . $query : ''));
});
Route::post('/signin', function (Request $request) use ($safeAuthRedirect) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
        'next' => ['nullable', 'string', 'max:2048'],
    ]);

    $email = strtolower(trim((string) $credentials['email']));
    $next = $safeAuthRedirect($credentials['next'] ?? null);

    if (! Auth::attempt(
        ['email' => $email, 'password' => $credentials['password']],
        $request->boolean('remember')
    )) {
        $query = http_build_query(array_filter([
            'error' => 'invalid',
            'email' => $email,
            'next' => $next !== '/account/profile' ? $next : null,
        ]));

        return redirect('/signin?' . $query);
    }

    $request->session()->regenerate();

    return redirect($next);
});
Route::middleware('auth')->post('/entry/{listing:slug}/reviews', function (Request $request, Listing $listing) {
    abort_unless($listing->status === 'published', 404);

    $user = Auth::user();
    $accountType = (string) ($user?->account_type ?? 'business');

    if ($accountType !== 'user') {
        return response()->json([
            'message' => 'Only Monaclick user accounts can submit reviews.',
        ], 403);
    }

    if ((int) $listing->user_id === (int) Auth::id()) {
        return response()->json([
            'message' => 'You cannot review your own listing.',
        ], 403);
    }

    $data = $request->validate([
        'rating' => ['required', 'integer', 'between:1,5'],
        'comment' => ['required', 'string', 'min:10', 'max:5000'],
    ]);

    $review = Review::query()->firstOrNew([
        'listing_id' => $listing->id,
        'user_id' => Auth::id(),
    ]);

    $review->rating = (int) $data['rating'];
    $review->comment = trim((string) $data['comment']);
    $review->is_approved = true;
    $review->save();

    $approvedReviews = Review::query()
        ->where('listing_id', $listing->id)
        ->where('is_approved', true);

    $listing->forceFill([
        'reviews_count' => (int) $approvedReviews->count(),
        'rating' => round((float) ($approvedReviews->avg('rating') ?? 0), 1),
    ])->save();

    $reviewerName = trim((string) (
        (($user?->account_type ?? 'business') === 'business'
            ? ($user?->company_name ?? '')
            : (($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''))
        )
    ));
    if ($reviewerName === '') {
        $reviewerName = trim((string) ($user?->name ?? 'Monaclick User'));
    }

    return response()->json([
        'ok' => true,
        'message' => 'Thanks! Your review has been received.',
        'review' => [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'comment' => (string) $review->comment,
            'author_name' => $reviewerName !== '' ? $reviewerName : 'Monaclick User',
            'listing_title' => (string) $listing->title,
            'listing_slug' => (string) $listing->slug,
            'listing_module' => (string) $listing->module,
            'created_at' => optional($review->updated_at)->toDateTimeString(),
        ],
        'listing' => [
            'rating' => (float) $listing->rating,
            'reviews_count' => (int) $listing->reviews_count,
        ],
    ]);
});
Route::get('/signout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/signin');
});
Route::post('/password-recovery', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'email'],
    ]);

    $exists = User::query()->where('email', strtolower($data['email']))->exists();
    $status = $exists ? 'sent' : 'missing';

    return redirect('/password-recovery?status=' . $status . '&email=' . urlencode($data['email']));
});
