<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->string('monitor_via', 10)->default('api')->after('ip');
            $table->string('snmp_version', 10)->default('2c')->after('password');
            $table->string('snmp_community')->nullable()->after('snmp_version');
            $table->unsignedSmallInteger('snmp_port')->default(161)->after('snmp_community');
            $table->unsignedSmallInteger('snmp_timeout')->default(3)->after('snmp_port');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn([
                'monitor_via',
                'snmp_version',
                'snmp_community',
                'snmp_port',
                'snmp_timeout',
            ]);
        });
    }
};
