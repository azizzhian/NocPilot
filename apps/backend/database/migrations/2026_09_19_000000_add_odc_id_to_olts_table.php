<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            if (! Schema::hasColumn('olts', 'odc_id')) {
                $table->foreignId('odc_id')
                    ->nullable()
                    ->after('pop_id')
                    ->constrained('odcs')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            if (Schema::hasColumn('olts', 'odc_id')) {
                $table->dropConstrainedForeignId('odc_id');
            }
        });
    }
};
