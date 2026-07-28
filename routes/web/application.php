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

// Dynamic version (kept separately)
Route::prefix('app')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('app.home');
    Route::get('/listings', [ListingController::class, 'index'])->name('app.listings.index');
    Route::get('/listings/{module}', [ListingController::class, 'index'])
        ->where('module', 'contractors')
        ->name('app.listings.module');
    Route::get('/entry/{listing:slug}', [ListingController::class, 'show'])->name('app.listings.show');
});

// Public JSON endpoints for static Finder pages wiring
Route::prefix('api/monaclick')->group(function () {
    Route::get('/listings', [PublicListingController::class, 'index']);
    Route::get('/entry', [PublicListingController::class, 'show']);
    Route::post('/claims/{listing:slug}/request-otp', [ListingClaimController::class, 'requestOtp']);
    Route::post('/claims/{listing:slug}/verify-otp', [ListingClaimController::class, 'verifyOtp']);
    Route::post('/claims/{listing:slug}/complete', [ListingClaimController::class, 'complete']);
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/taxonomy/{type}', [TaxonomyController::class, 'index'])
        ->whereIn('type', ['features', 'amenities', 'services']);
    Route::get('/locations/states', [LocationController::class, 'states']);
    Route::get('/locations/cities', [LocationController::class, 'cities']);
});

Route::get('/dashboard', function () {
    return redirect('/admin');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->prefix('admin/exports')->group(function () {
    Route::get('/listings.csv', [ExportController::class, 'listings']);
    Route::get('/reports.csv', [ExportController::class, 'reports']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

