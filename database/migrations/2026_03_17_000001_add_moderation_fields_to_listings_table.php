<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (!Schema::hasColumn('listings', 'admin_status')) {
                $table->string('admin_status', 20)->default('approved')->after('status');
            }
            if (!Schema::hasColumn('listings', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('admin_status');
            }
            if (!Schema::hasColumn('listings', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('listings', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            }
        });

        // Best-effort backfill for existing data.
        try {
            DB::table('listings')
                ->whereNull('admin_status')
                ->update(['admin_status' => 'approved']);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            if (Schema::hasColumn('listings', 'reviewed_by')) {
                $table->dropColumn('reviewed_by');
            }
            if (Schema::hasColumn('listings', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('listings', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('listings', 'admin_status')) {
                $table->dropColumn('admin_status');
            }
        });
    }
};

