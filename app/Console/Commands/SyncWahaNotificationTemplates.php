<?php

namespace App\Console\Commands;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use Illuminate\Console\Command;

class SyncWahaNotificationTemplates extends Command
{
    protected $signature = 'notifications:sync-waha-templates';

    protected $description = 'Sincroniza settings y plantillas WAHA (WhatsApp) con valores recomendados.';

    public function handle(): int
    {
        Setting::setValue('notifications.enabled', true, type: 'bool', description: 'Habilitar envío de notificaciones.');
        Setting::setValue('notifications.due_soon_days_list', '2,1', type: 'string', description: 'Lista de días antes del vencimiento (separado por comas).');
        Setting::setValue('notifications.overdue_reminder_every_days', 2, type: 'int', description: 'Frecuencia (días) para recordar cuotas vencidas.');
        Setting::setValue('notifications.send_start_hour', 9, type: 'int', description: 'Hora inicio (0-23) para envío de notificaciones.');
        Setting::setValue('notifications.send_end_hour', 19, type: 'int', description: 'Hora fin (0-23) para envío de notificaciones.');
        Setting::setValue('notifications.max_daily_per_customer', 3, type: 'int', description: 'Máximo de mensajes por cliente por día.');

        NotificationTemplate::query()->updateOrCreate(
            ['key' => 'waha_credit_created'],
            [
                'channel' => 'waha',
                'event' => 'credit_created',
                'enabled' => true,
                'body' => "Hola {{cliente}} 👋\n\nTu crédito ha sido registrado correctamente.\n\n📦 Productos: {{productos}}\n💰 Total: S/ {{monto_total}}\n📅 Cuotas: {{cuotas}}\n\nTe avisaremos antes de cada vencimiento.\n\nGracias 🙌",
                'variables' => ['cliente', 'credito', 'productos', 'monto_total', 'cuotas'],
            ],
        );

        NotificationTemplate::query()->updateOrCreate(
            ['key' => 'waha_installment_due_soon'],
            [
                'channel' => 'waha',
                'event' => 'installment_due_soon',
                'enabled' => true,
                'body' => "Hola {{cliente}} 👋\n\nTe recordamos que tu cuota vence pronto:\n\n📅 Fecha: {{fecha}}\n💰 Monto: S/ {{monto}}\n\nEvita retrasos realizando tu pago a tiempo 🙌",
                'variables' => ['cliente', 'credito', 'cuota', 'fecha', 'monto', 'dias'],
            ],
        );

        NotificationTemplate::query()->updateOrCreate(
            ['key' => 'waha_installment_overdue'],
            [
                'channel' => 'waha',
                'event' => 'installment_overdue',
                'enabled' => true,
                'body' => "Hola {{cliente}} 👋\n\nTu cuota está vencida:\n\n📅 Fecha: {{fecha}}\n💰 Monto pendiente: S/ {{monto_pendiente}}\n\nPor favor regulariza tu pago lo antes posible 🙏",
                'variables' => ['cliente', 'credito', 'cuota', 'fecha', 'monto_pendiente', 'dias_vencido'],
            ],
        );

        NotificationTemplate::query()->updateOrCreate(
            ['key' => 'waha_payment_received'],
            [
                'channel' => 'waha',
                'event' => 'payment_received',
                'enabled' => true,
                'body' => "Hola {{cliente}} 👋\n\nHemos recibido tu pago correctamente ✅\n\n💰 Monto: S/ {{monto}}\n📅 Fecha: {{fecha}}\n\nGracias por tu puntualidad 🙌",
                'variables' => ['cliente', 'credito', 'cuota', 'monto', 'fecha'],
            ],
        );

        $this->info('Settings y plantillas WAHA sincronizadas.');

        return self::SUCCESS;
    }
}
