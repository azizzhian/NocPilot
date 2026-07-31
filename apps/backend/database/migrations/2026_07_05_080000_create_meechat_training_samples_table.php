<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meechat_training_samples', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 120);
            $table->date('report_date');
            $table->string('phone_normalized', 30)->nullable();
            $table->string('conversation_id', 100)->nullable();
            $table->string('customer_name')->nullable();
            $table->json('messages');
            $table->unsignedSmallInteger('message_count')->default(0);
            $table->string('summarized_problem')->nullable();
            $table->string('corrected_problem')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('summarizer', 50)->default('rule_v1');
            $table->foreignId('daily_complaint_id')->nullable()->constrained('daily_complaints')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_key', 'report_date']);
            $table->index(['phone_normalized', 'report_date']);
        });

        $this->consolidateFromMessageLogs();
    }

    public function down(): void
    {
        Schema::dropIfExists('meechat_training_samples');
    }

    protected function consolidateFromMessageLogs(): void
    {
        if (! Schema::hasTable('meechat_message_logs')) {
            return;
        }

        $logs = DB::table('meechat_message_logs')->orderBy('id')->get();
        $groups = [];

        foreach ($logs as $log) {
            $phone = $log->phone_normalized;
            $groupKey = $phone ? 'phone:'.$phone : 'conv:'.$log->conversation_id;
            $date = $log->report_date;
            $key = $groupKey.'|'.$date;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'group_key' => $groupKey,
                    'report_date' => $date,
                    'phone_normalized' => $phone,
                    'conversation_id' => $log->conversation_id,
                    'customer_name' => null,
                    'messages' => [],
                    'summarized_problem' => $log->summarized_problem,
                    'corrected_problem' => $log->corrected_problem ?? null,
                    'reviewed_at' => $log->reviewed_at ?? null,
                    'summarizer' => $log->summarizer ?? 'rule_v1',
                    'daily_complaint_id' => $log->daily_complaint_id,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                ];
            }

            $decoded = json_decode($log->messages, true) ?: [];
            foreach ($decoded as $item) {
                $text = is_array($item) ? trim((string) ($item['text'] ?? '')) : trim((string) $item);
                if ($text !== '' && ! in_array($text, $groups[$key]['messages'], true)) {
                    $groups[$key]['messages'][] = $text;
                }
            }

            if (filled($log->corrected_problem)) {
                $groups[$key]['corrected_problem'] = $log->corrected_problem;
            }
            if (filled($log->reviewed_at)) {
                $groups[$key]['reviewed_at'] = $log->reviewed_at;
            }
            if ($log->daily_complaint_id) {
                $groups[$key]['daily_complaint_id'] = $log->daily_complaint_id;
            }
            $groups[$key]['updated_at'] = $log->updated_at;
        }

        foreach ($groups as $group) {
            $messages = array_map(fn (string $text) => ['text' => $text, 'at' => null], $group['messages']);

            DB::table('meechat_training_samples')->insert([
                'group_key' => $group['group_key'],
                'report_date' => $group['report_date'],
                'phone_normalized' => $group['phone_normalized'],
                'conversation_id' => $group['conversation_id'],
                'customer_name' => $group['customer_name'],
                'messages' => json_encode($messages, JSON_UNESCAPED_UNICODE),
                'message_count' => count($messages),
                'summarized_problem' => $group['summarized_problem'],
                'corrected_problem' => $group['corrected_problem'],
                'reviewed_at' => $group['reviewed_at'],
                'summarizer' => $group['summarizer'],
                'daily_complaint_id' => $group['daily_complaint_id'],
                'created_at' => $group['created_at'] ?? now(),
                'updated_at' => $group['updated_at'] ?? now(),
            ]);
        }
    }
};
