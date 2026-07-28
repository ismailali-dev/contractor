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


// Contractor-only public frontend.
Route::get('/', fn () => $serve('home-contractors.html'))->name('home');
Route::get('/contractors', fn () => $serve('home-contractors.html'))->name('contractors.home');
Route::get('/listings', fn () => $serve('listings-contractors.html'))->name('listings.index');
Route::get('/listings/contractors', fn () => $serve('listings-contractors.html'))->name('listings.module');
Route::get('/entry/contractors', fn () => $serve('single-entry-contractors.html'))->name('finder.entry');
Route::get('/claim/{module}', function (string $module) use ($serve) {
    abort_unless($module === 'contractors', 404);
    return $serve('claim-business.html');
});
Route::get('/add-listing', function (Request $request) use ($requireListingAuth) {
    if ($redirect = $requireListingAuth($request)) {
        return $redirect;
    }
    return redirect('/add-contractor');
});
Route::get('/add-contractor', function (Request $request) use ($serve, $requireListingAuth) {
    if ($redirect = $requireListingAuth($request)) {
        return $redirect;
    }
    $editId = (int) $request->query('edit', 0);
    if ($editId > 0) {
        if (! Auth::check()) {
            return redirect('/signin');
        }
        $exists = Listing::query()
            ->where('id', $editId)
            ->where('module', 'contractors')
            ->where('user_id', Auth::id())
            ->exists();
        if (! $exists) {
            return redirect('/account/listings?error=invalid-edit');
        }
    }
    return $serve('add-contractor-location.html');
});
Route::get('/add-contractor-services', function (Request $request) use ($serve, $requireListingAuth) {
    if ($redirect = $requireListingAuth($request)) {
        return $redirect;
    }
    $editId = (int) $request->query('edit', 0);
    if ($editId > 0) {
        if (! Auth::check()) {
            return redirect('/signin');
        }
        $exists = Listing::query()
            ->where('id', $editId)
            ->where('module', 'contractors')
            ->where('user_id', Auth::id())
            ->exists();
        if (! $exists) {
            return redirect('/account/listings?error=invalid-edit');
        }
    }
    return $serve('add-contractor-services.html');
});
Route::get('/add-contractor-price-hours', function (Request $request) use ($serve, $requireListingAuth) {
    if ($redirect = $requireListingAuth($request)) {
        return $redirect;
    }
    $editId = (int) $request->query('edit', 0);
    if ($editId > 0) {
        if (! Auth::check()) {
            return redirect('/signin');
        }
        $exists = Listing::query()
            ->where('id', $editId)
            ->where('module', 'contractors')
            ->where('user_id', Auth::id())
            ->exists();
        if (! $exists) {
            return redirect('/account/listings?error=invalid-edit');
        }
    }
    return $serve('add-contractor-price-hours.html');
});
Route::get('/add-contractor-project', function (Request $request) use ($serve, $requireListingAuth) {
    if ($redirect = $requireListingAuth($request)) {
        return $redirect;
    }
    $editId = (int) $request->query('edit', 0);
    if ($editId > 0) {
        if (! Auth::check()) {
            return redirect('/signin');
        }
        $exists = Listing::query()
            ->where('id', $editId)
            ->where('module', 'contractors')
            ->where('user_id', Auth::id())
            ->exists();
        if (! $exists) {
            return redirect('/account/listings?error=invalid-edit');
        }
    }
    return $serve('add-contractor-project.html');
});
Route::get('/add-contractor-promotion', function (Request $request) use ($serve, $requireListingAuth) {
    if ($redirect = $requireListingAuth($request)) {
        return $redirect;
    }
    $editId = (int) $request->query('edit', 0);
    if ($editId > 0) {
        if (! Auth::check()) {
            return redirect('/signin');
        }
        $exists = Listing::query()
            ->where('id', $editId)
            ->where('module', 'contractors')
            ->where('user_id', Auth::id())
            ->exists();
        if (! $exists) {
            return redirect('/account/listings?error=invalid-edit');
        }
    }
    return $serve('add-contractor-promotion.html');
});
Route::get('/add-contractor-profile', function (Request $request) use ($serve, $requireListingAuth) {
    if ($redirect = $requireListingAuth($request)) {
        return $redirect;
    }
    $editId = (int) $request->query('edit', 0);
    if ($editId > 0) {
        if (! Auth::check()) {
            return redirect('/signin');
        }
        $exists = Listing::query()
            ->where('id', $editId)
            ->where('module', 'contractors')
            ->where('user_id', Auth::id())
            ->exists();
        if (! $exists) {
            return redirect('/account/listings?error=invalid-edit');
        }
    }
    return $serve('add-contractor-profile.html');
});
Route::match(['get', 'post'], '/submit/contractor', [ListingSubmissionController::class, 'contractor']);
Route::get('/add-contractor-location', function (Request $request) use ($serve, $requireListingAuth) {
    if ($redirect = $requireListingAuth($request)) {
        return $redirect;
    }
    return $serve('add-contractor-location.html');
});
Route::get('/about', fn () => $serve('about-v2.html'));
Route::get('/blog', fn () => $serve('blog-layout-v1.html'));
Route::get('/videos/{slug}', function (string $slug) use ($serve) {
    $videos = [
        'electric-mercedes-sedan-car-reportedly-debuting-in-2025' => [
            'title' => 'Electric Mercedes sedan car reportedly debuting in 2025',
            'image' => '/finder/assets/img/blog/v2/vlog/01.jpg',
        ],
        'budget-vs-premium-tyres-which-are-better-value-this-year' => [
            'title' => 'Budget vs Premium tyres: which are better value this year?',
            'image' => '/finder/assets/img/blog/v2/vlog/02.jpg',
        ],
        'tesla-fixes-common-recall-with-over-the-air-update' => [
            'title' => 'Tesla fixes common recall with over-the-air update',
            'image' => '/finder/assets/img/blog/v2/vlog/03.jpg',
        ],
    ];

    abort_unless(isset($videos[$slug]), 404);
    $video = $videos[$slug];
    $response = $serve('blog-single-v2.html');
    $html = str_replace(
        [
            'Monaclick | Blog Single Post v.2',
            "Ford Edge to be discontinued in 2025, won't return for 2026",
            '/finder/assets/img/blog/v2/single/01.jpg',
        ],
        [
            'Monaclick | ' . $video['title'],
            $video['title'],
            $video['image'],
        ],
        $response->getContent()
    );
    $response->setContent($html);

    return $response;
})->where('slug', '[a-z0-9-]+');
Route::get('/contact', fn () => $serve('contact-v2.html'));
Route::get('/newsletter', fn () => $serve('newsletter.html'));
Route::post('/newsletter', function (Request $request) {
    $email = trim((string) $request->input('email'));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return redirect('/newsletter?invalid=1&email=' . urlencode($email));
    }

    return redirect('/newsletter?subscribed=1&email=' . urlencode($email));
});
Route::get('/terms-and-conditions', fn () => $serve('terms-and-conditions.html'));
Route::get('/privacy-policy', fn () => $serve('privacy-policy.html'));
Route::get('/help-topics-v1.html', fn () => $serve('help-topics-v1.html'));
Route::get('/help-topics-v2.html', fn () => $serve('help-topics-v2.html'));
Route::get('/help-topics-v3.html', fn () => $serve('help-topics-v3.html'));
Route::get('/help-single-article-v1.html', fn () => $serve('help-single-article-v1.html'));
Route::get('/help-single-article-v2.html', fn () => $serve('help-single-article-v2.html'));
Route::get('/help-single-article-v3.html', fn () => $serve('help-single-article-v3.html'));
Route::get('/help-center', fn () => redirect('/help-topics-v1.html'));
