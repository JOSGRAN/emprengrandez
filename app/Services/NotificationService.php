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

        $vars = [
            'cliente' => $customer->name,
            'credito' => $credit->code,
            'monto' => number_format((float) $credit->total_amount, 2, '.', ''),
        ];

        $message = $this->render(
            event: 'credit_created',
            vars: $vars,
            fallback: 'Hola {{cliente}}, tu crédito {{credito}} fue creado por S/ {{monto}}.',
        );

        return $this->queueMessage(
            event: 'credit_created',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $credit->id,
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

        $vars = [
            'cliente' => $customer->name,
            'monto' => number_format((float) $payment->amount, 2, '.', ''),
        ];

        $message = $this->render(
            event: 'payment_received',
            vars: $vars,
            fallback: 'Hola {{cliente}}, pago recibido correctamente por S/ {{monto}}.',
        );

        return $this->queueMessage(
            event: 'payment_received',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $payment->credit_id,
            installmentId: $payment->installment_id,
            paymentId: $payment->id,
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
            'fecha' => $dueDate->format('Y-m-d'),
            'monto' => number_format((float) $installment->total_amount, 2, '.', ''),
            'cuota' => (string) $installment->number,
            'credito' => $credit->code,
        ];

        $message = $this->render(
            event: 'installment_due_soon',
            vars: $vars,
            fallback: 'Hola {{cliente}}, tu cuota vence el {{fecha}} por S/ {{monto}}.',
        );

        return $this->queueMessage(
            event: 'installment_due_soon',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $credit->id,
            installmentId: $installment->id,
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

        $vars = [
            'cliente' => $customer->name,
            'cuota' => (string) $installment->number,
            'monto' => number_format((float) $installment->total_amount, 2, '.', ''),
            'fecha' => CarbonImmutable::parse($installment->due_date)->format('Y-m-d'),
            'credito' => $credit->code,
        ];

        $message = $this->render(
            event: 'installment_overdue',
            vars: $vars,
            fallback: 'Hola {{cliente}}, tu cuota #{{cuota}} está vencida. Monto: S/ {{monto}}.',
        );

        return $this->queueMessage(
            event: 'installment_overdue',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $credit->id,
            installmentId: $installment->id,
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
            'fecha' => $dueDate->format('Y-m-d'),
            'monto' => number_format((float) $installment->total_amount, 2, '.', ''),
            'cuota' => (string) $installment->number,
            'credito' => $credit->code,
        ];

        $message = $this->render(
            event: 'installment_due_soon',
            vars: $vars,
            fallback: 'Hola {{cliente}}, tu cuota vence el {{fecha}} por S/ {{monto}}.',
        );

        return $this->queueMessage(
            event: 'installment_due_soon',
            to: $customer->whatsapp,
            message: $message,
            customerId: $customer->id,
            creditId: $credit->id,
            installmentId: $installment->id,
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
    ): WhatsAppMessageLog {
        $log = WhatsAppMessageLog::query()->create([
            'channel' => 'wava',
            'event' => $event,
            'customer_id' => $customerId,
            'credit_id' => $creditId,
            'installment_id' => $installmentId,
            'payment_id' => $paymentId,
            'to' => $to,
            'message' => $message,
            'status' => 'queued',
        ]);

        SendWhatsAppMessageJob::dispatch($log->id);

        return $log;
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
        $cacheKey = 'notification-template:wava:'.$event;

        return Cache::remember($cacheKey, 300, function () use ($event) {
            return NotificationTemplate::query()
                ->where('channel', 'wava')
                ->where('event', $event)
                ->where('enabled', true)
                ->first();
        });
    }
}
