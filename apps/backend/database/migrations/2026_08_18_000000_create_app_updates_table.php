<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_updates', function (Blueprint $table) {
            $table->id();
            $table->string('from_commit', 40)->nullable();
            $table->string('to_commit', 40);
            $table->string('branch')->nullable();
            $table->json('changes');
            $table->timestamp('deployed_at');
            $table->timestamps();

            $table->index('deployed_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('last_read_app_update_id')
                ->nullable()
                ->after('last_login_at')
                ->constrained('app_updates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_read_app_update_id');
        });

        Schema::dropIfExists('app_updates');
    }
};
