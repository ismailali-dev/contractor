<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });

        // Seed baseline feature terms used by existing listings/wizards.
        $now = now();
        DB::table('taxonomy_terms')->insert([
            [
                'type' => 'feature',
                'name' => 'Eco-friendly',
                'slug' => 'eco-friendly',
                'is_active' => true,
                'sort_order' => 10,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'feature',
                'name' => 'Free consultation',
                'slug' => 'free-consultation',
                'is_active' => true,
                'sort_order' => 20,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'feature',
                'name' => 'Online consultation',
                'slug' => 'online-consultation',
                'is_active' => true,
                'sort_order' => 30,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'feature',
                'name' => 'Free estimate',
                'slug' => 'free-estimate',
                'is_active' => true,
                'sort_order' => 40,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'feature',
                'name' => 'Verified hires',
                'slug' => 'verified-hires',
                'is_active' => true,
                'sort_order' => 50,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'feature',
                'name' => 'Weekend consultations',
                'slug' => 'weekend-consultations',
                'is_active' => true,
                'sort_order' => 60,
                'meta' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
