<?php

namespace App\Services;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Credit;
use App\Models\Installment;
use App\Models\NotificationTemplate;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\WhatsAppMessageLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    public function queueCreditCreated(Credit $credit): ?WhatsAppMessageLog
    {
        if (! Setting::getBool('notifications.enabled', true)) {
            return null;
        }

        $customer = $credit->customer;
        if (! $customer || blank($customer->whatsapp)) {
            return null;
        }

        $items = $credit->items()->with(['product'])->get();
        $productsText = $items
            ->map(function ($item): string {
                $name = (string) optional($item->product)->name;
                $qty = (int) ($item->quantity ?? 0);
                if ($name === '') {
                    return '';
                }

                return $qty > 1 ? ($name.' x'.$qty) : $name;
            })
            ->filter()
            ->values()
            ->implode(', ');

        if ($productsText === '') {
            $productsText = '-';
        }

        $vars = [
            'cliente' => $customer->name,
            'credito' => $credit->code,
            'monto_total' => number_format((float) $credit->total_amount, 2, '.', ''),
            'cuotas' => (string) ((int) ($credit->installments_count ?? $credit->installments()->count())),
            'productos' => $productsText,
        ];

        $message = $this->render(
            event: 'credit_created',
            vars: $vars,
            fallback: "Hola {{cliente}} 👋\n\nTu crédito ha sido registrado correctamente.\n\n📦 Productos: {{productos}}\n💰 Total: S/ {{monto_total}}\n📅 Cuotas: {{cuotas}}\n\nTe avisaremos antes de cada vencimiento.\n\nGracias 🙌",
        );

        return $this->queueMessage(
            event: 'credit_created',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $credit->id,
            context: [
                'credit_id' => (int) $credit->id,
            ],
        );
    }

    public function queuePaymentReceived(Payment $payment): ?WhatsAppMessageLog
    {
        if (! Setting::getBool('notifications.enabled', true)) {
            return null;
        }

        $customer = $payment->customer;
        if (! $customer || blank($customer->whatsapp)) {
            return null;
        }

        $creditCode = (string) optional($payment->credit)->code;
        $installmentNumber = $payment->installment ? (string) $payment->installment->number : '';
        $paidOn = $payment->paid_on ? CarbonImmutable::parse($payment->paid_on) : CarbonImmutable::today();

        $vars = [
            'cliente' => $customer->name,
            'monto' => number_format((float) $payment->amount, 2, '.', ''),
            'fecha' => $paidOn->format('d/m/Y'),
            'credito' => $creditCode !== '' ? $creditCode : (string) ($payment->credit_id ?? ''),
            'cuota' => $installmentNumber,
        ];

        $message = $this->render(
            event: 'payment_received',
            vars: $vars,
            fallback: "Hola {{cliente}} 👋\n\nHemos recibido tu pago correctamente ✅\n\n💰 Monto: S/ {{monto}}\n📅 Fecha: {{fecha}}\n\nGracias por tu puntualidad 🙌",
        );

        return $this->queueMessage(
            event: 'payment_received',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $payment->credit_id,
            installmentId: $payment->installment_id,
            paymentId: $payment->id,
            context: [
                'payment_id' => (int) $payment->id,
            ],
        );
    }

    public function queueInstallmentDueSoon(Installment $installment, CarbonImmutable $today, int $daysBefore = 1): ?WhatsAppMessageLog
    {
        if (! Setting::getBool('notifications.enabled', true)) {
            return null;
        }

        $credit = $installment->credit;
        $customer = $credit?->customer;
        if (! $credit || ! $customer || blank($customer->whatsapp)) {
            return null;
        }

        $dueDate = CarbonImmutable::parse($installment->due_date);
        if ($dueDate->diffInDays($today, false) !== -$daysBefore) {
            return null;
        }

        $vars = [
            'cliente' => $customer->name,
            'fecha' => $dueDate->format('d/m/Y'),
            'monto' => number_format((float) $installment->total_amount, 2, '.', ''),
            'cuota' => (string) $installment->number,
            'credito' => $credit->code,
            'dias' => (string) $daysBefore,
        ];

        $message = $this->render(
            event: 'installment_due_soon',
            vars: $vars,
            fallback: "Hola {{cliente}} 👋\n\nTe recordamos que tu cuota vence pronto:\n\n📅 Fecha: {{fecha}}\n💰 Monto: S/ {{monto}}\n\nEvita retrasos realizando tu pago a tiempo 🙌",
        );

        return $this->queueMessage(
            event: 'installment_due_soon',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $credit->id,
            installmentId: $installment->id,
            context: [
                'days_before' => $daysBefore,
                'due_date' => (string) $installment->due_date,
            ],
        );
    }

    public function queueInstallmentOverdue(Installment $installment): ?WhatsAppMessageLog
    {
        if (! Setting::getBool('notifications.enabled', true)) {
            return null;
        }

        $credit = $installment->credit;
        $customer = $credit?->customer;
        if (! $credit || ! $customer || blank($customer->whatsapp)) {
            return null;
        }

        $dueDate = CarbonImmutable::parse($installment->due_date);
        $today = CarbonImmutable::today();
        $daysOverdue = $dueDate->startOfDay()->diffInDays($today->startOfDay());
        $pending = max(0, (float) $installment->total_amount - (float) $installment->paid_amount);

        $vars = [
            'cliente' => $customer->name,
            'cuota' => (string) $installment->number,
            'fecha' => $dueDate->format('d/m/Y'),
            'credito' => $credit->code,
            'monto_pendiente' => number_format($pending, 2, '.', ''),
            'dias_vencido' => (string) $daysOverdue,
        ];

        $message = $this->render(
            event: 'installment_overdue',
            vars: $vars,
            fallback: "Hola {{cliente}} 👋\n\nTu cuota está vencida:\n\n📅 Fecha: {{fecha}}\n💰 Monto pendiente: S/ {{monto_pendiente}}\n\nPor favor regulariza tu pago lo antes posible 🙏",
        );

        return $this->queueMessage(
            event: 'installment_overdue',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $credit->id,
            installmentId: $installment->id,
            context: [
                'due_date' => (string) $installment->due_date,
            ],
        );
    }

    public function queueInstallmentManualReminder(Installment $installment): ?WhatsAppMessageLog
    {
        if (! Setting::getBool('notifications.enabled', true)) {
            return null;
        }

        $credit = $installment->credit;
        $customer = $credit?->customer;
        if (! $credit || ! $customer || blank($customer->whatsapp)) {
            return null;
        }

        if ($installment->status === 'overdue') {
            return $this->queueInstallmentOverdue($installment);
        }

        $dueDate = CarbonImmutable::parse($installment->due_date);
        $vars = [
            'cliente' => $customer->name,
            'fecha' => $dueDate->format('d/m/Y'),
            'monto' => number_format((float) $installment->total_amount, 2, '.', ''),
            'cuota' => (string) $installment->number,
            'credito' => $credit->code,
        ];

        $message = $this->render(
            event: 'installment_due_soon',
            vars: $vars,
            fallback: "Hola {{cliente}} 👋\n\nTe recordamos que tu cuota vence pronto:\n\n📅 Fecha: {{fecha}}\n💰 Monto: S/ {{monto}}\n\nEvita retrasos realizando tu pago a tiempo 🙌",
        );

        return $this->queueMessage(
            event: 'installment_due_soon',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $credit->id,
            installmentId: $installment->id,
            context: [
                'manual' => true,
                'due_date' => (string) $installment->due_date,
            ],
            forceSend: true,
        );
    }

    private function queueMessage(
        string $event,
        string $to,
        string $message,
        ?int $customerId = null,
        ?int $creditId = null,
        ?int $installmentId = null,
        ?int $paymentId = null,
        array $context = [],
        bool $forceSend = false,
    ): ?WhatsAppMessageLog {
        if (! $forceSend && ! $this->canSendNow()) {
            return null;
        }

        if (! $forceSend && $customerId && $this->exceedsDailyLimit($customerId)) {
            return null;
        }

        $fingerprint = $this->fingerprint(
            event: $event,
            to: $to,
            customerId: $customerId,
            creditId: $creditId,
            installmentId: $installmentId,
            paymentId: $paymentId,
            context: $context,
        );

        try {
            $log = WhatsAppMessageLog::query()->create([
                'channel' => 'waha',
                'event' => $event,
                'customer_id' => $customerId,
                'credit_id' => $creditId,
                'installment_id' => $installmentId,
                'payment_id' => $paymentId,
                'to' => $to,
                'message' => $message,
                'status' => 'queued',
                'fingerprint' => $fingerprint,
                'context' => $context,
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicateKeyException($e)) {
                return null;
            }

            throw $e;
        }

        SendWhatsAppMessageJob::dispatch($log->id);

        return $log;
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode() ?? '');
        if ($sqlState !== '23000') {
            return false;
        }

        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if ($driverCode === 1062) {
            return true;
        }

        return str_contains(strtolower($e->getMessage()), 'duplicate');
    }

    private function canSendNow(): bool
    {
        $startHour = Setting::getInt('notifications.send_start_hour', 9);
        $endHour = Setting::getInt('notifications.send_end_hour', 19);

        $startHour = max(0, min(23, $startHour));
        $endHour = max(0, min(23, $endHour));

        $now = CarbonImmutable::now();
        $hour = (int) $now->format('G');

        if ($startHour === $endHour) {
            return true;
        }

        if ($startHour < $endHour) {
            return $hour >= $startHour && $hour < $endHour;
        }

        return $hour >= $startHour || $hour < $endHour;
    }

    private function exceedsDailyLimit(int $customerId): bool
    {
        $max = max(1, Setting::getInt('notifications.max_daily_per_customer', 3));
        $today = CarbonImmutable::today()->toDateString();

        $count = WhatsAppMessageLog::query()
            ->where('customer_id', $customerId)
            ->whereDate('created_at', '=', $today)
            ->count();

        return $count >= $max;
    }

    private function fingerprint(
        string $event,
        string $to,
        ?int $customerId,
        ?int $creditId,
        ?int $installmentId,
        ?int $paymentId,
        array $context,
    ): string {
        $today = CarbonImmutable::today()->toDateString();

        $payload = [
            'event' => $event,
            'to' => trim($to),
            'customer_id' => $customerId,
            'credit_id' => $creditId,
            'installment_id' => $installmentId,
            'payment_id' => $paymentId,
            'context' => $context,
            'date' => $today,
        ];

        return hash('sha256', (string) json_encode($payload));
    }

    private function render(string $event, array $vars, string $fallback): string
    {
        $template = $this->getTemplate($event);
        $body = $template?->body ?: $fallback;

        return $this->renderBody($body, $vars);
    }

    private function renderBody(string $body, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $body = str_replace(['{{'.$key.'}}', '{'.$key.'}'], (string) $value, $body);
        }

        return $body;
    }

    private function getTemplate(string $event): ?NotificationTemplate
    {
        $cacheKey = 'notification-template:waha:'.$event;

        return Cache::remember($cacheKey, 300, function () use ($event) {
            return NotificationTemplate::query()
                ->where('channel', 'waha')
                ->where('event', $event)
                ->where('enabled', true)
                ->first();
        });
    }
}
