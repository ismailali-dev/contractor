<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('budget_tier')->default(2)->after('price')->index();
            $table->boolean('availability_now')->default(true)->after('budget_tier')->index();
            $table->json('features')->nullable()->after('availability_now');
        });

        $verifiedByListing = DB::table('contractor_details')->pluck('is_verified', 'listing_id');

        DB::table('listings')
            ->select(['id', 'module', 'price'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($verifiedByListing): void {
                foreach ($rows as $row) {
                    preg_match('/\d[\d,]*/', (string) $row->price, $matches);
                    $amount = isset($matches[0]) ? (int) str_replace(',', '', $matches[0]) : 0;

                    $budgetTier = match (true) {
                        $amount <= 100 => 1,
                        $amount <= 1000 => 2,
                        $amount <= 5000 => 3,
                        default => 4,
                    };

                    $features = [];
                    if ($row->module === 'contractors') {
                        $features[] = 'eco-friendly';
                        if ((int) ($verifiedByListing[$row->id] ?? 0) === 1) {
                            $features[] = 'verified-hires';
                        }
                        if ($row->id % 2 === 0) {
                            $features[] = 'free-consultation';
                        }
                        if ($row->id % 3 === 0) {
                            $features[] = 'weekend-consultations';
                        }
                        if ($row->id % 4 === 0) {
                            $features[] = 'online-consultation';
                        }
                        if ($row->id % 5 === 0) {
                            $features[] = 'free-estimate';
                        }
                    }

                    DB::table('listings')
                        ->where('id', $row->id)
                        ->update([
                            'budget_tier' => $budgetTier,
                            'availability_now' => $row->id % 3 !== 0,
                            'features' => json_encode(array_values(array_unique($features)), JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn(['budget_tier', 'availability_now', 'features']);
        });
    }
};
