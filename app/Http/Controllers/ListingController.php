<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Models\Listing;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function finderShow(Request $request, string $module)
    {
        $query = Listing::query()
            ->with([
                'category',
                'city',
                'images',
                'contractorDetail',
                'propertyDetail',
                'carDetail',
                'eventDetail',
            ])
            ->whereRaw('LOWER(TRIM(status)) = ?', ['published'])
            ->whereRaw('LOWER(TRIM(module)) = ?', [strtolower(trim($module))]);

        if ($request->filled('slug')) {
            $query->where('slug', (string) $request->string('slug'));
        }

        $listing = $query->latest('published_at')->firstOrFail();

        $related = Listing::query()
            ->with(['category', 'city'])
            ->whereRaw('LOWER(TRIM(status)) = ?', ['published'])
            ->whereRaw('LOWER(TRIM(module)) = ?', [strtolower(trim($module))])
            ->whereKeyNot($listing->id)
            ->latest('published_at')
            ->take(4)
            ->get();

        return view('finder.entry', compact('listing', 'related'));
    }

    public function finderIndex(Request $request, ?string $module = null)
    {
        $selectedModule = 'contractors';

        $query = Listing::with(['category', 'city'])->whereRaw('LOWER(TRIM(status)) = ?', ['published']);

        if ($selectedModule) {
            $query->whereRaw('LOWER(TRIM(module)) = ?', [strtolower(trim($selectedModule))]);
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($builder) use ($request) {
                $builder->where('slug', $request->string('category'));
            });
        }

        if ($request->filled('city')) {
            $query->whereHas('city', function ($builder) use ($request) {
                $builder->where('slug', $request->string('city'));
            });
        }

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($builder) use ($term) {
                $builder->where('title', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%");
            });
        }

        $listings = $query->latest('published_at')->paginate(8)->withQueryString();

        $categories = Category::query()
            ->when($selectedModule, fn ($builder) => $builder->where('module', $selectedModule))
            ->latest('id')
            ->get();

        $cities = City::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('finder.listings', compact('listings', 'categories', 'cities', 'selectedModule'));
    }

    public function index(Request $request, ?string $module = null)
    {
        $selectedModule = 'contractors';

        $query = Listing::with(['category', 'city'])
            ->whereRaw('LOWER(TRIM(status)) = ?', ['published']);

        if ($selectedModule) {
            $query->whereRaw('LOWER(TRIM(module)) = ?', [strtolower(trim($selectedModule))]);
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($builder) use ($request) {
                $builder->where('slug', $request->string('category'));
            });
        }

        if ($request->filled('city')) {
            $query->whereHas('city', function ($builder) use ($request) {
                $builder->where('slug', $request->string('city'));
            });
        }

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($builder) use ($term) {
                $builder->where('title', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%");
            });
        }

        $listings = $query->latest('published_at')->paginate(12)->withQueryString();

        $categories = Category::query()
            ->when($selectedModule, fn ($builder) => $builder->where('module', $selectedModule))
            ->where('is_active', true)
            ->latest('id')
            ->get();

        $cities = City::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('listings.index', compact('listings', 'categories', 'cities', 'selectedModule'));
    }

    public function show(Request $request, Listing $listing)
    {
        abort_unless(in_array(strtolower(trim((string) $listing->module)), ['contractors', 'contractor'], true), 404);
        $status = strtolower(trim((string) $listing->status));
        $isPreview = $request->boolean('preview');
        $isAdmin = auth()->check() && auth()->user()?->hasRole('admin');

        if ($status !== 'published' && !($isPreview && $isAdmin)) {
            abort(404);
        }

        $listing->loadMissing([
            'category',
            'city',
            'images',
            'contractorDetail',
            'propertyDetail',
            'carDetail',
            'eventDetail',
        ]);

        return view('listings.show', compact('listing'));
    }
}
