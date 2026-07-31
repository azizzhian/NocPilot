<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'daily_activations',
        'daily_cctv_setups',
        'daily_dismantles',
        'daily_complaints',
        'daily_noc_updates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('cleared_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                $blueprint->timestamp('cleared_at')->nullable()->after('cleared_by');
            });

            // Data lama yang sudah Clear: atribusi clear ke pembuat (fallback).
            DB::table($table)
                ->where('status', 'Clear')
                ->whereNull('cleared_by')
                ->whereNotNull('created_by')
                ->update([
                    'cleared_by' => DB::raw('created_by'),
                    'cleared_at' => DB::raw('updated_at'),
                ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('cleared_by');
                $blueprint->dropColumn('cleared_at');
            });
        }
    }
};
