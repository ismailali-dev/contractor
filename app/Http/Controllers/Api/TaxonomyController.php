<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxonomyTerm;
use Illuminate\Http\JsonResponse;

class TaxonomyController extends Controller
{
    public function index(string $type): JsonResponse
    {
        $allowed = [
            'features' => TaxonomyTerm::TYPE_FEATURE,
            'amenities' => TaxonomyTerm::TYPE_AMENITY,
            'services' => TaxonomyTerm::TYPE_SERVICE,
        ];

        abort_unless(array_key_exists($type, $allowed), 404);

        $terms = TaxonomyTerm::query()
            ->type($allowed[$type])
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->map(fn (TaxonomyTerm $term) => [
                'name' => $term->name,
                'slug' => $term->slug,
            ])
            ->values();

        return response()->json([
            'data' => $terms,
        ]);
    }
}

