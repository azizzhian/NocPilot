<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->foreignId('parent_complaint_id')
                ->nullable()
                ->after('shift')
                ->constrained('daily_complaints')
                ->nullOnDelete();
            $table->foreignId('complaint_group_id')
                ->nullable()
                ->after('parent_complaint_id')
                ->constrained('daily_complaints')
                ->nullOnDelete();
            $table->timestamp('handled_at')->nullable()->after('cleared_at');
            $table->foreignId('handled_by')
                ->nullable()
                ->after('handled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('auto_cleared')->default(false)->after('handled_by');
            $table->unsignedInteger('handling_minutes')->nullable()->after('auto_cleared');
            $table->string('performance_category', 20)->nullable()->after('handling_minutes');

            $table->index('complaint_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_complaint_id');
            $table->dropConstrainedForeignId('complaint_group_id');
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn(['auto_cleared', 'handling_minutes', 'performance_category']);
        });
    }
};
