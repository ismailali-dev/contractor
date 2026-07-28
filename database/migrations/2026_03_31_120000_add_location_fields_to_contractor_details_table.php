<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractor_details', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractor_details', 'address_line')) {
                $table->string('address_line')->nullable()->after('service_area');
            }
            if (! Schema::hasColumn('contractor_details', 'zip_code')) {
                $table->string('zip_code')->nullable()->after('address_line');
            }
            if (! Schema::hasColumn('contractor_details', 'state_code')) {
                $table->string('state_code', 8)->nullable()->after('zip_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contractor_details', function (Blueprint $table): void {
            foreach (['state_code', 'zip_code', 'address_line'] as $column) {
                if (Schema::hasColumn('contractor_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
