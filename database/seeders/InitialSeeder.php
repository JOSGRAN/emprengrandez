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
        Setting::setValue('notifications.overdue_reminder_every_days', 1, type: 'int', description: 'Frecuencia (días) para recordar cuotas vencidas.');

        NotificationTemplate::query()->firstOrCreate(
            ['key' => 'wava_credit_created'],
            [
                'channel' => 'wava',
                'event' => 'credit_created',
                'enabled' => true,
                'body' => 'Hola {{cliente}}, tu crédito {{credito}} fue creado por S/ {{monto}}.',
                'variables' => ['cliente', 'credito', 'monto'],
            ],
        );

        NotificationTemplate::query()->firstOrCreate(
            ['key' => 'wava_installment_due_soon'],
            [
                'channel' => 'wava',
                'event' => 'installment_due_soon',
                'enabled' => true,
                'body' => 'Hola {{cliente}}, tu cuota vence el {{fecha}} por S/ {{monto}}.',
                'variables' => ['cliente', 'fecha', 'monto', 'cuota', 'credito'],
            ],
        );

        NotificationTemplate::query()->firstOrCreate(
            ['key' => 'wava_installment_overdue'],
            [
                'channel' => 'wava',
                'event' => 'installment_overdue',
                'enabled' => true,
                'body' => 'Hola {{cliente}}, tu cuota #{{cuota}} está vencida. Monto: S/ {{monto}}.',
                'variables' => ['cliente', 'cuota', 'monto', 'fecha', 'credito'],
            ],
        );

        NotificationTemplate::query()->firstOrCreate(
            ['key' => 'wava_payment_received'],
            [
                'channel' => 'wava',
                'event' => 'payment_received',
                'enabled' => true,
                'body' => 'Hola {{cliente}}, pago recibido correctamente por S/ {{monto}}.',
                'variables' => ['cliente', 'monto'],
            ],
        );
    }
}
