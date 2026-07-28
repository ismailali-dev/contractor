<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocationController extends Controller
{
    public function states(): JsonResponse
    {
        $fallback = [
            ['code' => 'AL', 'name' => 'Alabama'],
            ['code' => 'AK', 'name' => 'Alaska'],
            ['code' => 'AZ', 'name' => 'Arizona'],
            ['code' => 'AR', 'name' => 'Arkansas'],
            ['code' => 'CA', 'name' => 'California'],
            ['code' => 'CO', 'name' => 'Colorado'],
            ['code' => 'CT', 'name' => 'Connecticut'],
            ['code' => 'DE', 'name' => 'Delaware'],
            ['code' => 'DC', 'name' => 'District of Columbia'],
            ['code' => 'FL', 'name' => 'Florida'],
            ['code' => 'GA', 'name' => 'Georgia'],
            ['code' => 'HI', 'name' => 'Hawaii'],
            ['code' => 'ID', 'name' => 'Idaho'],
            ['code' => 'IL', 'name' => 'Illinois'],
            ['code' => 'IN', 'name' => 'Indiana'],
            ['code' => 'IA', 'name' => 'Iowa'],
            ['code' => 'KS', 'name' => 'Kansas'],
            ['code' => 'KY', 'name' => 'Kentucky'],
            ['code' => 'LA', 'name' => 'Louisiana'],
            ['code' => 'ME', 'name' => 'Maine'],
            ['code' => 'MD', 'name' => 'Maryland'],
            ['code' => 'MA', 'name' => 'Massachusetts'],
            ['code' => 'MI', 'name' => 'Michigan'],
            ['code' => 'MN', 'name' => 'Minnesota'],
            ['code' => 'MS', 'name' => 'Mississippi'],
            ['code' => 'MO', 'name' => 'Missouri'],
            ['code' => 'MT', 'name' => 'Montana'],
            ['code' => 'NE', 'name' => 'Nebraska'],
            ['code' => 'NV', 'name' => 'Nevada'],
            ['code' => 'NH', 'name' => 'New Hampshire'],
            ['code' => 'NJ', 'name' => 'New Jersey'],
            ['code' => 'NM', 'name' => 'New Mexico'],
            ['code' => 'NY', 'name' => 'New York'],
            ['code' => 'NC', 'name' => 'North Carolina'],
            ['code' => 'ND', 'name' => 'North Dakota'],
            ['code' => 'OH', 'name' => 'Ohio'],
            ['code' => 'OK', 'name' => 'Oklahoma'],
            ['code' => 'OR', 'name' => 'Oregon'],
            ['code' => 'PA', 'name' => 'Pennsylvania'],
            ['code' => 'PR', 'name' => 'Puerto Rico'],
            ['code' => 'RI', 'name' => 'Rhode Island'],
            ['code' => 'SC', 'name' => 'South Carolina'],
            ['code' => 'SD', 'name' => 'South Dakota'],
            ['code' => 'TN', 'name' => 'Tennessee'],
            ['code' => 'TX', 'name' => 'Texas'],
            ['code' => 'UT', 'name' => 'Utah'],
            ['code' => 'VT', 'name' => 'Vermont'],
            ['code' => 'VA', 'name' => 'Virginia'],
            ['code' => 'WA', 'name' => 'Washington'],
            ['code' => 'WV', 'name' => 'West Virginia'],
            ['code' => 'WI', 'name' => 'Wisconsin'],
            ['code' => 'WY', 'name' => 'Wyoming'],
        ];

        if (!Schema::hasTable('states')) {
            // Safe fallback when migrations haven't run yet.
            return response()->json(['data' => $fallback]);
        }

        $states = Cache::remember('locations:states:us', now()->addHours(12), function () use ($fallback) {
            $cityStateCodes = [];

            if (Schema::hasTable('cities') && Schema::hasColumn('cities', 'state_code')) {
                $cityStateCodes = DB::table('cities')
                    ->selectRaw('DISTINCT UPPER(TRIM(state_code)) as code')
                    ->whereNotNull('state_code')
                    ->whereRaw("TRIM(state_code) <> ''")
                    ->pluck('code')
                    ->map(fn ($code) => strtoupper(trim((string) $code)))
                    ->filter(fn ($code) => preg_match('/^[A-Z]{2}$/', $code) === 1)
                    ->values()
                    ->all();
            }

            if (!empty($cityStateCodes)) {
                if (Schema::hasTable('states')) {
                    $states = State::query()
                        ->where('country_code', 'US')
                        ->where('is_active', true)
                        ->whereIn('code', $cityStateCodes)
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get(['code', 'name'])
                        ->map(fn (State $state) => [
                            'code' => $state->code,
                            'name' => $state->name,
                        ])
                        ->values()
                        ->all();

                    if (!empty($states)) {
                        return $states;
                    }
                }

                $fallbackMap = collect($fallback)->keyBy(fn ($item) => strtoupper((string) ($item['code'] ?? '')));

                $states = collect($cityStateCodes)
                    ->map(fn ($code) => $fallbackMap->get($code))
                    ->filter(fn ($item) => is_array($item) && !empty($item['code']) && !empty($item['name']))
                    ->sortBy('name')
                    ->values()
                    ->all();

                if (!empty($states)) {
                    return $states;
                }
            }

            return State::query()
                ->where('country_code', 'US')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn (State $state) => [
                    'code' => $state->code,
                    'name' => $state->name,
                ])
                ->values()
                ->all();
        });

        if (empty($states)) {
            return response()->json(['data' => $fallback]);
        }

        return response()->json(['data' => $states]);
    }

    public function cities(Request $request): JsonResponse
    {
        $state = strtoupper(trim($request->string('state')->toString()));
        abort_unless($state !== '' && preg_match('/^[A-Z]{2}$/', $state) === 1, 400);

        $hasStateCode = Schema::hasColumn('cities', 'state_code');
        if (!$hasStateCode) {
            return response()->json(['data' => []]);
        }

        if (Schema::hasTable('states')) {
            $hasAnyStates = State::query()->where('country_code', 'US')->exists();
            if ($hasAnyStates) {
                $exists = State::query()
                    ->where('country_code', 'US')
                    ->where('code', $state)
                    ->where('is_active', true)
                    ->exists();
                abort_unless($exists, 404);
            }
        }

        $cities = Cache::remember("locations:cities:{$state}", now()->addHours(12), function () use ($state) {
            $hasIsActive = Schema::hasColumn('cities', 'is_active');
            $hasSortOrder = Schema::hasColumn('cities', 'sort_order');

            $citiesQuery = City::query()
                // Be tolerant to legacy/dirty imports (lowercase or extra spaces).
                ->whereRaw('UPPER(TRIM(state_code)) = ?', [$state]);

            if ($hasIsActive) {
                // Treat NULL as active (common when importing via phpMyAdmin/CSV with empty values).
                $citiesQuery->where(function ($q) {
                    $q->whereNull('is_active')->orWhere('is_active', true);
                });
            }

            if ($hasSortOrder) {
                $citiesQuery->orderBy('sort_order');
            }

            $baseQuery = clone $citiesQuery;

            $fetch = function ($query) {
                return $query
                    ->orderBy('name')
                    ->get(['name', 'slug'])
                    ->unique(fn (City $city) => mb_strtolower(trim((string) $city->name)))
                    ->map(fn (City $city) => [
                        'name' => $city->name,
                        'slug' => $city->slug,
                    ])
                    ->values()
                    ->all();
            };

            $cities = $fetch($citiesQuery);

            // If data exists but flags are wrong (e.g. imported is_active=0), don't show an empty dropdown.
            if ($hasIsActive && empty($cities)) {
                $cities = $fetch($baseQuery);
            }

            return $cities;
        });

        return response()->json(['data' => $cities]);
    }
}
