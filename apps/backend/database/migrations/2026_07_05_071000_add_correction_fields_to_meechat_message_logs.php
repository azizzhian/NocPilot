<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meechat_message_logs', function (Blueprint $table) {
            $table->string('corrected_problem')->nullable()->after('summarized_problem');
            $table->timestamp('reviewed_at')->nullable()->after('corrected_problem');
        });
    }

    public function down(): void
    {
        Schema::table('meechat_message_logs', function (Blueprint $table) {
            $table->dropColumn(['corrected_problem', 'reviewed_at']);
        });
    }
};
