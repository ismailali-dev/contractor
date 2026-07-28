<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_claim_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('otp_hash');
            $table->dateTime('otp_expires_at');
            $table->unsignedSmallInteger('otp_attempts')->default(0);
            $table->dateTime('verified_at')->nullable();
            $table->string('claim_token_hash')->nullable();
            $table->dateTime('claim_token_expires_at')->nullable();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('used_at')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'email']);
            $table->index('used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_claim_requests');
    }
};
