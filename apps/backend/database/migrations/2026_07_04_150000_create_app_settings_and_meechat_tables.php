<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('meechat_complaint_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 100)->unique();
            $table->foreignId('daily_complaint_id')->constrained('daily_complaints')->cascadeOnDelete();
            $table->string('phone_normalized', 30)->nullable();
            $table->string('customer_name')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meechat_complaint_syncs');
        Schema::dropIfExists('app_settings');
    }
};
