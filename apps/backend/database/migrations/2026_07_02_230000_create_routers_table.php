<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('ip');
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('pop')->nullable();
            $table->string('area')->nullable();
            $table->string('status')->default('offline');
            $table->unsignedTinyInteger('cpu')->default(0);
            $table->unsignedTinyInteger('memory')->default(0);
            $table->unsignedTinyInteger('temperature')->default(0);
            $table->unsignedSmallInteger('voltage')->nullable();
            $table->string('uptime')->nullable();
            $table->unsignedInteger('clients')->default(0);
            $table->unsignedInteger('pppoe_sessions')->default(0);
            $table->string('board')->nullable();
            $table->string('version')->nullable();
            $table->string('license')->nullable();
            $table->unsignedBigInteger('download_bps')->default(0);
            $table->unsignedBigInteger('upload_bps')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('sync_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['status', 'pop']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
