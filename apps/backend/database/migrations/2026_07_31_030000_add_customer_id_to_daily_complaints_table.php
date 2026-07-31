<?php

use App\Models\Customer;
use App\Models\DailyComplaint;
use App\Services\Phone\PhoneNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('report_date')
                ->constrained('customers')
                ->nullOnDelete();
            $table->index('customer_id');
        });

        DailyComplaint::query()
            ->whereNull('customer_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $customerId = null;

                    if ($row->phone_normalized) {
                        $local = PhoneNormalizer::toLocal((string) $row->phone_normalized) ?: $row->phone_normalized;
                        $customerId = Customer::query()
                            ->where(function ($q) use ($local, $row) {
                                $q->where('phone', $local)
                                    ->orWhere('phone', $row->phone_normalized);
                            })
                            ->value('id');
                    }

                    if (! $customerId && $row->customer_name) {
                        $name = trim((string) $row->customer_name);
                        // Strip " (ODC ...)" suffix from autocomplete display names.
                        $name = preg_replace('/\s*\(.*\)\s*$/', '', $name) ?: $name;
                        $customerId = Customer::query()
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                            ->value('id');
                    }

                    if ($customerId) {
                        $row->customer_id = $customerId;
                        $row->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('daily_complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
