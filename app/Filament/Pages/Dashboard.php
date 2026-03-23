<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function booted(): void
    {
        $payload = session()->pull('security_new_device');
        if (! is_array($payload)) {
            return;
        }

        $device = (string) ($payload['device'] ?? 'Dispositivo');
        $ip = (string) ($payload['ip'] ?? '');

        $message = $device;
        if ($ip !== '') {
            $message .= ' · IP: '.$ip;
        }

        Notification::make()
            ->title('Nuevo dispositivo detectado')
            ->body($message)
            ->warning()
            ->send();
    }
}
