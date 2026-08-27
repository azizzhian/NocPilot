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
            $table->foreignId('cleared_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('cleared_at')->nullable()->after('cleared_by');
        });

        // Backfill data Clear lama: anggap clearer = pembuat tiket.
        DB::table('dismantles')
            ->where('status', 'Clear')
            ->whereNull('cleared_by')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $clearedAt = $row->closed_at
                        ?: $row->completed_at
                        ?: $row->updated_at
                        ?: $row->created_at;

                    DB::table('dismantles')->where('id', $row->id)->update([
                        'cleared_by' => $row->created_by,
                        'cleared_at' => $clearedAt,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('dismantles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cleared_by');
            $table->dropColumn('cleared_at');
        });
    }
};
