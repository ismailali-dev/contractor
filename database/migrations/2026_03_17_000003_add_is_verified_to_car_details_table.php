<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_details', function (Blueprint $table): void {
            if (!Schema::hasColumn('car_details', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('condition');
            }
        });
    }

    public function down(): void
    {
        Schema::table('car_details', function (Blueprint $table): void {
            if (Schema::hasColumn('car_details', 'is_verified')) {
                $table->dropColumn('is_verified');
            }
        });
    }
};

