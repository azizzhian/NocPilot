<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_activations', function (Blueprint $table) {
            $table->string('package_name')->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('daily_activations', function (Blueprint $table) {
            $table->dropColumn('package_name');
        });
    }
};
