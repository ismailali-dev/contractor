<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->default('US')->index();
            $table->string('code', 2)->index();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['country_code', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};

