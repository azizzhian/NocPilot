<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_activations', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('customer_name');
            $table->string('olt_name')->nullable();
            $table->string('port_onu')->nullable();
            $table->string('status')->default('On-Progress');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('report_date');
        });

        Schema::create('daily_cctv_setups', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('customer_name')->nullable();
            $table->string('router')->nullable();
            $table->string('status')->default('On-Progress');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('report_date');
        });

        Schema::create('daily_dismantles', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('customer_name');
            $table->string('site_name')->nullable();
            $table->date('start_ticket')->nullable();
            $table->date('close_ticket')->nullable();
            $table->string('status')->default('On-Progress');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('report_date');
        });

        Schema::create('daily_complaints', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->string('odc_name')->nullable();
            $table->string('customer_name');
            $table->date('start_problem')->nullable();
            $table->date('end_problem')->nullable();
            $table->string('problem')->nullable();
            $table->text('action')->nullable();
            $table->string('status')->default('On-Progress');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('report_date');
        });

        Schema::create('daily_noc_updates', function (Blueprint $table) {
            $table->id();
            $table->date('report_date');
            $table->text('description');
            $table->string('status')->default('On-Progress');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_noc_updates');
        Schema::dropIfExists('daily_complaints');
        Schema::dropIfExists('daily_dismantles');
        Schema::dropIfExists('daily_cctv_setups');
        Schema::dropIfExists('daily_activations');
    }
};
