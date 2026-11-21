<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_gamas_updates', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('update_text');
            $table->timestamp('ended_at')->nullable()->after('started_at');
            $table->unsignedInteger('duration_minutes')->nullable()->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('daily_gamas_updates', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'ended_at', 'duration_minutes']);
        });
    }
};
