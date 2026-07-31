<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('router_interfaces', function (Blueprint $table) {
            $table->unsignedBigInteger('rx_bps')->default(0)->after('is_running');
            $table->unsignedBigInteger('tx_bps')->default(0)->after('rx_bps');
            $table->timestamp('traffic_polled_at')->nullable()->after('tx_bps');
        });
    }

    public function down(): void
    {
        Schema::table('router_interfaces', function (Blueprint $table) {
            $table->dropColumn(['rx_bps', 'tx_bps', 'traffic_polled_at']);
        });
    }
};
