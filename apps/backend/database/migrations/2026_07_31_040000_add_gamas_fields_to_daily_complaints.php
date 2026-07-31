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
            $table->string('complaint_type', 20)->default('individual')->after('report_date');
            $table->string('customer_code')->nullable()->after('customer_id');
            $table->string('gamas_kind', 30)->nullable()->after('customer_code');
            $table->string('location_label')->nullable()->after('gamas_kind');
            $table->string('impact')->nullable()->after('location_label');
            $table->index('complaint_type');
        });

        // Backfill customer_code from customers where linked.
        if (Schema::hasColumn('daily_complaints', 'customer_id')) {
            DB::table('daily_complaints')
                ->whereNotNull('customer_id')
                ->whereNull('customer_code')
                ->orderBy('id')
                ->chunkById(100, function ($rows) {
                    foreach ($rows as $row) {
                        $code = DB::table('customers')->where('id', $row->customer_id)->value('customer_code');
                        if ($code) {
                            DB::table('daily_complaints')->where('id', $row->id)->update([
                                'customer_code' => $code,
                            ]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->dropIndex(['complaint_type']);
            $table->dropColumn([
                'complaint_type',
                'customer_code',
                'gamas_kind',
                'location_label',
                'impact',
            ]);
        });
    }
};
