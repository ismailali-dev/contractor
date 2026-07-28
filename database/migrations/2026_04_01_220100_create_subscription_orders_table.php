<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('module', 50)->nullable();
            $table->string('package_slug', 100)->nullable();
            $table->string('package_label')->nullable();
            $table->string('package_price')->nullable();
            $table->json('selected_services')->nullable();
            $table->json('selected_services_details')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('admin_status', 30)->default('approved');
            $table->string('source', 50)->default('listing-sync');
            $table->string('snapshot_hash', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['listing_id', 'status']);
            $table->index(['admin_status', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_orders');
    }
};
