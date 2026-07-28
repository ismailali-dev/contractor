<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_details', function (Blueprint $table): void {
            $table->string('radius')->nullable()->after('mileage');
            $table->string('drive_type')->nullable()->after('radius');
            $table->string('engine')->nullable()->after('drive_type');
            $table->unsignedSmallInteger('city_mpg')->nullable()->after('body_type');
            $table->unsignedSmallInteger('highway_mpg')->nullable()->after('city_mpg');
            $table->string('exterior_color')->nullable()->after('highway_mpg');
            $table->string('interior_color')->nullable()->after('exterior_color');
            $table->string('seller_type')->nullable()->after('interior_color');
            $table->string('contact_first_name')->nullable()->after('seller_type');
            $table->string('contact_last_name')->nullable()->after('contact_first_name');
            $table->string('contact_email')->nullable()->after('contact_last_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->boolean('negotiated')->default(false)->after('contact_phone');
            $table->boolean('installments')->default(false)->after('negotiated');
            $table->boolean('exchange')->default(false)->after('installments');
            $table->boolean('uncleared')->default(false)->after('exchange');
            $table->boolean('dealer_ready')->default(false)->after('uncleared');
        });
    }

    public function down(): void
    {
        Schema::table('car_details', function (Blueprint $table): void {
            $table->dropColumn([
                'radius',
                'drive_type',
                'engine',
                'city_mpg',
                'highway_mpg',
                'exterior_color',
                'interior_color',
                'seller_type',
                'contact_first_name',
                'contact_last_name',
                'contact_email',
                'contact_phone',
                'negotiated',
                'installments',
                'exchange',
                'uncleared',
                'dealer_ready',
            ]);
        });
    }
};

