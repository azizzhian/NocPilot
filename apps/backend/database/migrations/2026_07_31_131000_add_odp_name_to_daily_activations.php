<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_activations', function (Blueprint $table) {
            $table->string('odp_name')->nullable()->after('olt_name');
        });
    }

    public function down(): void
    {
        Schema::table('daily_activations', function (Blueprint $table) {
            $table->dropColumn('odp_name');
        });
    }
};
