<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_details', function (Blueprint $table): void {
            $table->string('brand')->nullable()->after('listing_id');
            $table->string('model')->nullable()->after('brand');
            $table->string('condition')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('car_details', function (Blueprint $table): void {
            $table->dropColumn(['brand', 'model', 'condition']);
        });
    }
};

