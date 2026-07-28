<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cities')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table): void {
            // Laravel default index name for $table->string('slug')->unique() is `cities_slug_unique`.
            // Drop it if present and replace with a composite unique to allow duplicate city names across states.
            try {
                $table->dropUnique(['slug']);
            } catch (\Throwable $e) {
                // ignore if index doesn't exist (older installs / renamed indexes)
            }

            if (Schema::hasColumn('cities', 'state_code')) {
                try {
                    $table->unique(['state_code', 'slug']);
                } catch (\Throwable $e) {
                    // ignore if already exists
                }
                try {
                    $table->index(['state_code', 'name']);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cities')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table): void {
            if (Schema::hasColumn('cities', 'state_code')) {
                try {
                    $table->dropUnique(['state_code', 'slug']);
                } catch (\Throwable $e) {
                    // ignore
                }
                try {
                    $table->dropIndex(['state_code', 'name']);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            try {
                $table->unique(['slug']);
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};

