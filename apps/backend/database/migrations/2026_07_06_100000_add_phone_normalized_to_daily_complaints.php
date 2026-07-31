<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->string('phone_normalized', 30)->nullable()->after('customer_name');
        });

        DB::table('meechat_complaint_syncs')
            ->whereNotNull('daily_complaint_id')
            ->whereNotNull('phone_normalized')
            ->orderBy('id')
            ->each(function ($sync) {
                DB::table('daily_complaints')
                    ->where('id', $sync->daily_complaint_id)
                    ->whereNull('phone_normalized')
                    ->update(['phone_normalized' => $sync->phone_normalized]);
            });
    }

    public function down(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->dropColumn('phone_normalized');
        });
    }
};
