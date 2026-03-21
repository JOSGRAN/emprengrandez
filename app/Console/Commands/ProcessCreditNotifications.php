<?php

namespace App\Console\Commands;

use App\Models\Installment;
use App\Models\Setting;
use App\Models\WhatsAppMessageLog;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessCreditNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:process-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesa mora y envía notificaciones de cuotas (por vencer / vencidas).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = CarbonImmutable::today();
        $dueSoonDays = Setting::getInt('notifications.due_soon_days', 1);
        $dueSoonDate = $today->addDays(max(0, $dueSoonDays));

        $this->processDueSoon($today, $dueSoonDate, $dueSoonDays);
        $this->processOverdue($today);

        return self::SUCCESS;
    }

    private function processDueSoon(CarbonImmutable $today, CarbonImmutable $dueSoonDate, int $daysBefore): void
    {
        $service = app(NotificationService::class);

        Installment::query()
            ->where('status', 'pending')
            ->whereDate('due_date', '=', $dueSoonDate->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($installments) use ($service, $today, $daysBefore) {
                foreach ($installments as $installment) {
                    $alreadySent = WhatsAppMessageLog::query()
                        ->where('event', 'installment_due_soon')
                        ->where('installment_id', $installment->id)
                        ->whereDate('created_at', '=', $today->toDateString())
                        ->exists();

                    if ($alreadySent) {
                        continue;
                    }

                    $service->queueInstallmentDueSoon($installment, $today, $daysBefore);
                }
            });
    }

    private function processOverdue(CarbonImmutable $today): void
    {
        $service = app(NotificationService::class);
        $frequencyDays = max(1, Setting::getInt('notifications.overdue_reminder_every_days', 1));

        Installment::query()
            ->where('status', 'pending')
            ->whereDate('due_date', '<', $today->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($installments) {
                foreach ($installments as $installment) {
                    $installment->status = 'overdue';
                    $installment->save();
                }
            });

        Installment::query()
            ->where('status', 'overdue')
            ->whereDate('due_date', '<', $today->toDateString())
            ->orderBy('id')
            ->chunkById(200, function ($installments) use ($service, $today, $frequencyDays) {
                $ids = $installments->pluck('id')->all();

                $lastSent = DB::table('whats_app_message_logs')
                    ->where('event', 'installment_overdue')
                    ->whereIn('installment_id', $ids)
                    ->selectRaw('installment_id, MAX(created_at) as last_sent_at')
                    ->groupBy('installment_id')
                    ->pluck('last_sent_at', 'installment_id')
                    ->all();

                foreach ($installments as $installment) {
                    $last = $lastSent[$installment->id] ?? null;
                    if ($last) {
                        $lastDate = CarbonImmutable::parse($last)->startOfDay();
                        $days = $lastDate->diffInDays($today);
                        if ($days < $frequencyDays) {
                            continue;
                        }
                    }

                    $service->queueInstallmentOverdue($installment);
                }
            });
    }
}
