<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->unsignedTinyInteger('shift')->nullable()->after('status');
        });

        Schema::create('report_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('location')->nullable();
            $table->string('customer_code')->nullable();
            $table->string('customer_name');
            $table->string('problem')->nullable();
            $table->text('action')->nullable();
            $table->string('status')->default('On-Progress');
            $table->date('opened_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
            $table->index('customer_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_tickets');

        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
