<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_tickets', function (Blueprint $table) {
            $table->string('source', 20)->default('direct')->after('id');
            $table->foreignId('complaint_id')
                ->nullable()
                ->after('source')
                ->constrained('daily_complaints')
                ->nullOnDelete();
            $table->foreignId('customer_id')
                ->nullable()
                ->after('complaint_id')
                ->constrained('customers')
                ->nullOnDelete();
            $table->string('customer_phone', 30)->nullable()->after('customer_code');
            $table->string('job_type', 100)->nullable()->after('problem');
            $table->string('priority', 20)->nullable()->after('job_type');
            $table->dateTime('visit_schedule')->nullable()->after('priority');
            $table->string('source_complaint_number', 50)->nullable()->after('visit_schedule');
            $table->json('source_snapshot')->nullable()->after('source_complaint_number');
            $table->text('escalation_reason')->nullable()->after('notes');
            $table->timestamp('escalated_at')->nullable()->after('escalation_reason');
            $table->foreignId('assigned_to')
                ->nullable()
                ->after('escalated_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->timestamp('work_started_at')->nullable()->after('assigned_at');
            $table->timestamp('work_completed_at')->nullable()->after('work_started_at');

            $table->index(['source', 'status']);
            $table->index('complaint_id');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('report_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('complaint_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropIndex(['source', 'status']);
            $table->dropColumn([
                'source', 'customer_phone', 'job_type', 'priority', 'visit_schedule',
                'source_complaint_number', 'source_snapshot', 'escalation_reason',
                'escalated_at', 'assigned_at', 'work_started_at', 'work_completed_at',
            ]);
        });
    }
};
