<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique('categories_slug_unique');
            $table->unique(['module', 'slug'], 'categories_module_slug_unique');
            $table->index(['module', 'is_active', 'sort_order'], 'categories_module_active_sort_index');
        });

        Schema::table('cities', function (Blueprint $table): void {
            $table->index(['is_active', 'sort_order'], 'cities_active_sort_index');
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->index(['status', 'module', 'published_at'], 'listings_status_module_published_index');
            $table->index(['status', 'category_id', 'published_at'], 'listings_status_category_published_index');
            $table->index(['status', 'city_id', 'published_at'], 'listings_status_city_published_index');
        });

        Schema::table('listing_images', function (Blueprint $table): void {
            $table->index(['listing_id', 'sort_order'], 'listing_images_listing_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listing_images', function (Blueprint $table): void {
            $table->dropIndex('listing_images_listing_sort_index');
        });

        Schema::table('listings', function (Blueprint $table): void {
            $table->dropIndex('listings_status_module_published_index');
            $table->dropIndex('listings_status_category_published_index');
            $table->dropIndex('listings_status_city_published_index');
        });

        Schema::table('cities', function (Blueprint $table): void {
            $table->dropIndex('cities_active_sort_index');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_module_active_sort_index');
            $table->dropUnique('categories_module_slug_unique');
            $table->unique('slug');
        });
    }
};
