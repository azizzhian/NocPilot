<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_name');
            $table->string('activity_name');
            $table->longText('daily_report_text');
            $table->longText('noc_update_text');
            $table->longText('monitoring_report_text')->nullable();
            $table->timestamps();

            $table->index(['report_date', 'created_at']);
        });

        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique();
            $table->longText('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
        Schema::dropIfExists('daily_report_snapshots');
    }
};
