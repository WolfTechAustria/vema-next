<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prüft Captcha-Antworten von Cloudflare Turnstile. Die Schlüssel gelten
 * für die ganze Installation (config/services.php).
 */
class TurnstileVerifier
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function isConfigured(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->isConfigured() || blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $exception) {
            Log::warning('Turnstile-Prüfung fehlgeschlagen.', ['error' => $exception->getMessage()]);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
