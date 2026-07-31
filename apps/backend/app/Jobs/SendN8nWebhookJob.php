<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendN8nWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $url = config('services.n8n.webhook_url');

        if (! $url) {
            Log::info("N8N webhook skipped (no URL): {$this->event}", $this->payload);

            return;
        }

        try {
            Http::timeout(10)->post($url, [
                'event' => $this->event,
                'timestamp' => now()->toIso8601String(),
                'app' => 'NocPilot',
                'data' => $this->payload,
            ]);
        } catch (\Throwable $e) {
            Log::error("N8N webhook failed: {$this->event} — {$e->getMessage()}");
            throw $e;
        }
    }
}
