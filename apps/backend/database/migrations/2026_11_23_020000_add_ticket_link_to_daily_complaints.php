<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->foreignId('ticket_id')
                ->nullable()
                ->after('complaint_group_id')
                ->constrained('report_tickets')
                ->nullOnDelete();
            $table->timestamp('escalated_at')->nullable()->after('ticket_id');

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_id');
            $table->dropColumn('escalated_at');
        });
    }
};
