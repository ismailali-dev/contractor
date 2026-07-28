<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_orders')) {
            return;
        }

        DB::table('subscription_orders')
            ->where('admin_status', 'pending')
            ->update(['admin_status' => 'approved']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscription_orders')) {
            return;
        }

        DB::table('subscription_orders')
            ->where('admin_status', 'approved')
            ->update(['admin_status' => 'pending']);
    }
};
