<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contractor_details', 'profile_image_path')) {
            Schema::table('contractor_details', function (Blueprint $table): void {
                $table->string('profile_image_path')->nullable()->after('business_hours');
            });
        }

        $fallbackImages = DB::table('contractor_details')
            ->join('listings', 'listings.id', '=', 'contractor_details.listing_id')
            ->whereNull('contractor_details.profile_image_path')
            ->whereNotNull('listings.image')
            ->select('contractor_details.id', 'listings.image')
            ->get();

        foreach ($fallbackImages as $row) {
            DB::table('contractor_details')
                ->where('id', $row->id)
                ->update([
                    'profile_image_path' => $row->image,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contractor_details', 'profile_image_path')) {
            Schema::table('contractor_details', function (Blueprint $table): void {
                $table->dropColumn('profile_image_path');
            });
        }
    }
};
