<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_details', function (Blueprint $table): void {
            $table->json('wizard_data')->nullable()->after('dealer_ready');
        });
    }

    public function down(): void
    {
        Schema::table('car_details', function (Blueprint $table): void {
            $table->dropColumn('wizard_data');
        });
    }
};

