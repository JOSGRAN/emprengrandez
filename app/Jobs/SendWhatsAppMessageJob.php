<?php

namespace App\Jobs;

use App\Models\WhatsAppMessageLog;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $logId) {}

    public function handle(WhatsAppService $service): void
    {
        $log = WhatsAppMessageLog::query()->findOrFail($this->logId);

        if (in_array($log->status, ['sent'], true)) {
            return;
        }

        $log->attempts = (int) $log->attempts + 1;
        $log->status = 'sending';
        $log->save();

        $result = $service->sendTextMessage($log->to, $log->message);

        $log->status = 'sent';
        $log->sent_at = now();
        $log->provider_payload = $result['payload'] ?? null;
        $log->provider_response = $result['response'] ?? null;
        $log->save();
    }

    public function failed(\Throwable $e): void
    {
        $log = WhatsAppMessageLog::query()->find($this->logId);
        if (! $log) {
            return;
        }

        $log->status = 'failed';
        $log->last_error = $e->getMessage();
        $log->save();

        Log::error('WhatsApp send failed', [
            'log_id' => $this->logId,
            'exception' => $e->getMessage(),
        ]);
    }
}
