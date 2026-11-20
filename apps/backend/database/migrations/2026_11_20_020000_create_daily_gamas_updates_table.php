<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_gamas_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_gamas_id')->constrained('daily_gamas')->cascadeOnDelete();
            $table->text('update_text');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['daily_gamas_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_gamas_updates');
    }
};
