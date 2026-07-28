<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function finderHome(Request $request, ?string $module = 'contractors')
    {
        $selectedModule = $module ?: 'contractors';

        $featured = Listing::query()
            ->with(['category', 'city'])
            ->where('status', 'published')
            ->where('module', $selectedModule)
            ->latest('published_at')
            ->take(8)
            ->get();

        $categories = Category::query()
            ->where('module', $selectedModule)
            ->latest('id')
            ->take(8)
            ->get();

        $modules = collect(Listing::MODULE_OPTIONS);

        return view('finder.home', compact('featured', 'categories', 'modules', 'selectedModule'));
    }

    public function index()
    {
        $featured = Listing::with(['category', 'city'])
            ->where('status', 'published')
            ->latest('published_at')
            ->take(8)
            ->get();

        $modules = Category::query()
            ->select('module')
            ->where('is_active', true)
            ->distinct()
            ->pluck('module');

        return view('home.index', compact('featured', 'modules'));
    }
}
