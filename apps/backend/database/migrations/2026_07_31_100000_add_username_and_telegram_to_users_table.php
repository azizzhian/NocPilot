<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('telegram_id')->nullable()->unique()->after('email');
            $table->string('telegram_username')->nullable()->after('telegram_id');
        });

        User::query()->orderBy('id')->each(function (User $user) {
            if ($user->username) {
                return;
            }

            $base = Str::before((string) $user->email, '@');
            $base = Str::lower(preg_replace('/[^a-zA-Z0-9._-]/', '', $base) ?: 'user'.$user->id);
            $candidate = $base;
            $i = 1;
            while (User::where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
                $candidate = $base.$i;
                $i++;
            }

            $user->forceFill(['username' => $candidate])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['telegram_id']);
            $table->dropColumn(['username', 'telegram_id', 'telegram_username']);
        });
    }
};
