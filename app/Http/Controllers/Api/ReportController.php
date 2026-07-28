<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
            'slug' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'max:255'],
        ]);

        $listing = null;
        if (!empty($validated['listing_id'])) {
            $listing = Listing::query()->find((int) $validated['listing_id']);
        } elseif (!empty($validated['slug'])) {
            $listing = Listing::query()->where('slug', (string) $validated['slug'])->first();
        }

        if (!$listing) {
            return response()->json(['message' => 'Listing not found.'], 404);
        }

        $report = ListingReport::query()->create([
            'listing_id' => $listing->id,
            'reporter_user_id' => auth()->id(),
            'reporter_email' => $validated['email'] ?? null,
            'reporter_ip' => (string) ($request->ip() ?? ''),
            'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
            'reason' => strtolower(trim((string) $validated['reason'])),
            'message' => $validated['message'] ?? null,
            'status' => 'open',
        ]);

        return response()->json([
            'ok' => true,
            'id' => $report->id,
        ], 201);
    }
}

