<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InitialSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $sellerRole = Role::query()->firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);

        if (Permission::query()->count() === 0) {
            Artisan::call('shield:generate', [
                '--all' => true,
                '--panel' => 'admin',
                '--no-interaction' => true,
            ]);

            Artisan::call('shield:generate', [
                '--all' => true,
                '--panel' => 'superadmin',
                '--no-interaction' => true,
            ]);
        }

        $allPermissions = Permission::query()->pluck('name')->all();
        if (count($allPermissions) > 0) {
            $superAdminRole->syncPermissions($allPermissions);
            $adminRole->syncPermissions($allPermissions);

            $sellerPermissions = array_values(array_filter($allPermissions, function (string $p) {
                return str_contains($p, 'customer')
                    || str_contains($p, 'product')
                    || str_contains($p, 'credit')
                    || str_contains($p, 'payment')
                    || str_contains($p, 'view_dashboard');
            }));

            $sellerRole->syncPermissions($sellerPermissions);
        }

        $email = (string) env('SEED_SUPERADMIN_EMAIL', 'superadmin@local.test');
        $password = (string) env('SEED_SUPERADMIN_PASSWORD', 'password');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'SuperAdmin',
                'password' => Hash::make($password),
            ],
        );

        if (! $user->hasRole($superAdminRole->name)) {
            $user->assignRole($superAdminRole);
        }

        $wallet = Wallet::query()->firstOrCreate(
            ['name' => 'Caja principal'],
            [
                'balance' => 0,
                'currency' => 'PEN',
                'is_active' => true,
            ],
        );

        Setting::setValue('wallet.default_wallet_id', (int) $wallet->id, type: 'int', description: 'Billetera por defecto para pagos y gastos.');
        Setting::setValue('wallet.allow_negative', true, type: 'bool', description: 'Permitir saldo negativo en la billetera.');

        Setting::setValue('notifications.enabled', true, type: 'bool', description: 'Habilitar envío de notificaciones.');
        Setting::setValue('notifications.due_soon_days', 1, type: 'int', description: 'Días antes del vencimiento para recordar.');
        Setting::setValue('notifications.due_soon_days_list', '2,1', type: 'string', description: 'Lista de días antes del vencimiento (separado por comas).');
        Setting::setValue('notifications.overdue_reminder_every_days', 1, type: 'int', description: 'Frecuencia (días) para recordar cuotas vencidas.');
        Setting::setValue('notifications.send_start_hour', 9, type: 'int', description: 'Hora inicio (0-23) para envío de notificaciones.');
        Setting::setValue('notifications.send_end_hour', 19, type: 'int', description: 'Hora fin (0-23) para envío de notificaciones.');
        Setting::setValue('notifications.max_daily_per_customer', 3, type: 'int', description: 'Máximo de mensajes por cliente por día.');

        NotificationTemplate::query()->firstOrCreate(
            ['key' => 'waha_credit_created'],
            [
                'channel' => 'waha',
                'event' => 'credit_created',
                'enabled' => true,
                'body' => "Hola {{cliente}} 👋\n\nTu crédito ha sido registrado correctamente.\n\n📦 Productos: {{productos}}\n💰 Total: S/ {{monto_total}}\n📅 Cuotas: {{cuotas}}\n\nTe avisaremos antes de cada vencimiento.\n\nGracias 🙌",
                'variables' => ['cliente', 'credito', 'productos', 'monto_total', 'cuotas'],
            ],
        );

        NotificationTemplate::query()->firstOrCreate(
            ['key' => 'waha_installment_due_soon'],
            [
                'channel' => 'waha',
                'event' => 'installment_due_soon',
                'enabled' => true,
                'body' => "Hola {{cliente}} 👋\n\nTe recordamos que tu cuota vence pronto:\n\n📅 Fecha: {{fecha}}\n💰 Monto: S/ {{monto}}\n\nEvita retrasos realizando tu pago a tiempo 🙌",
                'variables' => ['cliente', 'credito', 'cuota', 'fecha', 'monto', 'dias'],
            ],
        );

        NotificationTemplate::query()->firstOrCreate(
            ['key' => 'waha_installment_overdue'],
            [
                'channel' => 'waha',
                'event' => 'installment_overdue',
                'enabled' => true,
                'body' => "Hola {{cliente}} 👋\n\nTu cuota está vencida:\n\n📅 Fecha: {{fecha}}\n💰 Monto pendiente: S/ {{monto_pendiente}}\n\nPor favor regulariza tu pago lo antes posible 🙏",
                'variables' => ['cliente', 'credito', 'cuota', 'fecha', 'monto_pendiente', 'dias_vencido'],
            ],
        );

        NotificationTemplate::query()->firstOrCreate(
            ['key' => 'waha_payment_received'],
            [
                'channel' => 'waha',
                'event' => 'payment_received',
                'enabled' => true,
                'body' => "Hola {{cliente}} 👋\n\nHemos recibido tu pago correctamente ✅\n\n💰 Monto: S/ {{monto}}\n📅 Fecha: {{fecha}}\n\nGracias por tu puntualidad 🙌",
                'variables' => ['cliente', 'credito', 'cuota', 'monto', 'fecha'],
            ],
        );
    }
}
