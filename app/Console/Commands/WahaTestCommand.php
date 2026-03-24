<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class WahaTestCommand extends Command
{
    protected $signature = 'waha:test {to} {message?}';

    protected $description = 'Enviar un mensaje de prueba por WAHA.';

    public function handle(WhatsAppService $service): int
    {
        $to = (string) $this->argument('to');
        $message = (string) ($this->argument('message') ?? 'Mensaje de prueba desde Emprengrandez.');

        $service->sendTextMessage($to, $message);

        $this->info('Mensaje enviado (request OK).');

        return self::SUCCESS;
    }
}
