<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'customer_code')) {
                $table->string('customer_code', 100)->nullable()->after('id');
            }
            if (! Schema::hasColumn('customers', 'odc_id')) {
                $table->foreignId('odc_id')->nullable()->after('address')->constrained('odcs')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'imported_at')) {
                $table->timestamp('imported_at')->nullable();
            }
        });

        DB::table('customers')->orderBy('id')->lazyById()->each(function ($customer) {
            if ($customer->customer_code) {
                return;
            }

            DB::table('customers')->where('id', $customer->id)->update([
                'customer_code' => $customer->pppoe ?: 'P'.str_pad((string) $customer->id, 5, '0', STR_PAD_LEFT),
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('customer_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'odc_id')) {
                $table->dropConstrainedForeignId('odc_id');
            }
            if (Schema::hasColumn('customers', 'customer_code')) {
                $table->dropUnique(['customer_code']);
                $table->dropColumn('customer_code');
            }
            if (Schema::hasColumn('customers', 'imported_at')) {
                $table->dropColumn('imported_at');
            }
        });
    }
};
