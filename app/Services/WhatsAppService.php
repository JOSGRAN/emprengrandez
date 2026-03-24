<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsAppService
{
    /**
     * @return array<string, mixed>
     */
    public function sendTextMessage(string $to, string $message): array
    {
        $baseUrl = rtrim((string) config('services.waha.base_url'), '/');
        $apiKey = (string) config('services.waha.api_key');
        $path = (string) config('services.waha.send_text_path');
        $session = (string) config('services.waha.session', 'default');

        if ($baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('WAHA credentials are not configured.');
        }
        if ($session === '') {
            throw new \RuntimeException('WAHA session is not configured.');
        }

        $payload = [
            'to' => $this->normalizePhone($to),
            'message' => $message,
            'session' => $session,
        ];

        $response = Http::baseUrl($baseUrl)
            ->withHeaders([
                'X-Api-Key' => $apiKey,
            ])
            ->acceptJson()
            ->asJson()
            ->post($path, $payload)
            ->throw();

        return [
            'payload' => $payload,
            'response' => $response->json(),
        ];
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $defaultCountry = preg_replace('/\D+/', '', (string) config('services.waha.default_country_code')) ?? '';

        if ($digits === '') {
            return $phone;
        }

        if ($defaultCountry !== '' && ! Str::startsWith($digits, $defaultCountry)) {
            if (Str::startsWith($digits, '0')) {
                $digits = ltrim($digits, '0');
            }
            $digits = $defaultCountry.$digits;
        }

        return '+'.$digits;
    }
}
