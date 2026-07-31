<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dismantles', function (Blueprint $table) {
            $table->string('location')->nullable()->after('customer_name');
            $table->string('customer_code')->nullable()->after('location');
            $table->date('opened_at')->nullable()->after('status');
            $table->date('closed_at')->nullable()->after('opened_at');
        });

        // Migrate existing data into the new columns where possible.
        if (Schema::hasTable('dismantles')) {
            DB::table('dismantles')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $status = match ($row->status) {
                        'completed' => 'Clear',
                        'in_progress', 'scheduled' => 'On-Progress',
                        default => 'Pending',
                    };

                    DB::table('dismantles')->where('id', $row->id)->update([
                        'location' => $row->area,
                        'customer_code' => $row->pppoe,
                        'opened_at' => $row->scheduled_at ? substr((string) $row->scheduled_at, 0, 10) : null,
                        'closed_at' => $row->completed_at ? substr((string) $row->completed_at, 0, 10) : null,
                        'status' => $status,
                    ]);
                }
            });
        }

        Schema::table('daily_dismantles', function (Blueprint $table) {
            $table->string('customer_code')->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('dismantles', function (Blueprint $table) {
            $table->dropColumn(['location', 'customer_code', 'opened_at', 'closed_at']);
        });

        Schema::table('daily_dismantles', function (Blueprint $table) {
            $table->dropColumn('customer_code');
        });
    }
};
