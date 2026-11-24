<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_ticket_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_ticket_id')->constrained('report_tickets')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->default('progress');
            $table->text('check_result')->nullable();
            $table->text('repair_action')->nullable();
            $table->string('material', 255)->nullable();
            $table->string('photo', 255)->nullable();
            $table->timestamp('work_started_at')->nullable();
            $table->timestamp('work_ended_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['report_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_ticket_updates');
    }
};
