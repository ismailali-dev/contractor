<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            if (!Schema::hasColumn('cities', 'state_code')) {
                $table->string('state_code', 2)->nullable()->index()->after('slug');
            }
            if (!Schema::hasColumn('cities', 'is_active')) {
                $table->boolean('is_active')->default(true)->index()->after('state_code');
            }
            if (!Schema::hasColumn('cities', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            if (Schema::hasColumn('cities', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
            if (Schema::hasColumn('cities', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('cities', 'state_code')) {
                $table->dropColumn('state_code');
            }
        });
    }
};

