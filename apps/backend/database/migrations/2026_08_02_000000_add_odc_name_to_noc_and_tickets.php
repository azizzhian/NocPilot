<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_noc_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('daily_noc_updates', 'odc_name')) {
                $table->string('odc_name')->nullable()->after('description');
            }
        });

        Schema::table('report_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('report_tickets', 'odc_name')) {
                $table->string('odc_name')->nullable()->after('location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_noc_updates', function (Blueprint $table) {
            if (Schema::hasColumn('daily_noc_updates', 'odc_name')) {
                $table->dropColumn('odc_name');
            }
        });

        Schema::table('report_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('report_tickets', 'odc_name')) {
                $table->dropColumn('odc_name');
            }
        });
    }
};
