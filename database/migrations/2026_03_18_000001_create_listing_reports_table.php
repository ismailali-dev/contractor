<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listing_reports')) {
            return;
        }

        Schema::create('listing_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('reporter_user_id')->nullable();
            $table->string('reporter_email')->nullable();
            $table->string('reporter_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('reason', 50);
            $table->text('message')->nullable();
            $table->string('status', 20)->default('open'); // open|resolved|dismissed
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->index('listing_id', 'listing_reports_listing_id_idx');
            $table->index('reporter_user_id', 'listing_reports_reporter_user_id_idx');
            $table->index('reporter_ip', 'listing_reports_reporter_ip_idx');
            $table->index('reason', 'listing_reports_reason_idx');
            $table->index('status', 'listing_reports_status_idx');
            $table->index('resolved_at', 'listing_reports_resolved_at_idx');
            $table->index('resolved_by', 'listing_reports_resolved_by_idx');

            $table->foreign('listing_id', 'listing_reports_listing_id_fk')
                ->references('id')
                ->on('listings')
                ->cascadeOnDelete();

            $table->foreign('reporter_user_id', 'listing_reports_reporter_user_id_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('resolved_by', 'listing_reports_resolved_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_reports');
    }
};
