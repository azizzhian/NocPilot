<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meechat_complaint_syncs', function (Blueprint $table) {
            $table->date('report_date')->nullable()->after('conversation_id');
        });

        \Illuminate\Support\Facades\DB::table('meechat_complaint_syncs')
            ->whereNull('report_date')
            ->update(['report_date' => now()->toDateString()]);

        Schema::table('meechat_complaint_syncs', function (Blueprint $table) {
            $table->dropUnique(['conversation_id']);
        });

        Schema::table('meechat_complaint_syncs', function (Blueprint $table) {
            $table->unique(['conversation_id', 'report_date']);
            $table->index(['phone_normalized', 'report_date']);
        });

        Schema::create('meechat_message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 100);
            $table->date('report_date');
            $table->string('phone_normalized', 30)->nullable();
            $table->json('messages');
            $table->string('summarized_problem')->nullable();
            $table->string('summarizer', 50)->default('rule_v1');
            $table->foreignId('daily_complaint_id')->nullable()->constrained('daily_complaints')->nullOnDelete();
            $table->timestamps();
            $table->index(['conversation_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meechat_message_logs');

        Schema::table('meechat_complaint_syncs', function (Blueprint $table) {
            $table->dropUnique(['conversation_id', 'report_date']);
            $table->dropIndex(['phone_normalized', 'report_date']);
        });

        Schema::table('meechat_complaint_syncs', function (Blueprint $table) {
            $table->unique('conversation_id');
            $table->dropColumn('report_date');
        });
    }
};
