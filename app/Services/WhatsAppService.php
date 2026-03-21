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
        $baseUrl = rtrim((string) config('services.wava.base_url'), '/');
        $token = (string) config('services.wava.token');
        $path = (string) config('services.wava.send_text_path');

        if ($baseUrl === '' || $token === '') {
            throw new \RuntimeException('Wava credentials are not configured.');
        }

        $payload = [
            'to' => $this->normalizePhone($to),
            'message' => $message,
        ];

        $response = Http::baseUrl($baseUrl)
            ->withToken($token)
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
        $defaultCountry = preg_replace('/\D+/', '', (string) config('services.wava.default_country_code')) ?? '';

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
