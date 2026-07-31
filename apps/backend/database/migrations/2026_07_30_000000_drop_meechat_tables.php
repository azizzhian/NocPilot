<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meechat_training_samples');
        Schema::dropIfExists('meechat_message_logs');
        Schema::dropIfExists('meechat_complaint_syncs');
    }

    public function down(): void
    {
        // Tabel MeeChat dihapus permanen — tidak di-restore.
    }
};
