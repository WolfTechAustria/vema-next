<?php

namespace App\Services;

use App\Mail\TwoFactorCodeMail;
use App\Models\Setting;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Zwei-Faktor-Anmeldung für Funktionäre: Code aus einer Authenticator-App
 * (TOTP nach RFC 6238) oder per E-Mail, dazu Wiederherstellungscodes und
 * „Diesem Gerät 30 Tage vertrauen“.
 */
class TwoFactorAuthentication
{
    public const METHOD_APP = 'app';

    public const METHOD_EMAIL = 'email';

    public const EMAIL_CODE_MINUTES = 10;

    public const TRUST_DAYS = 30;

    public const RECOVERY_CODE_COUNT = 8;

    private const PERIOD_SECONDS = 30;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Pflicht für alle Funktionäre, wenn der Verein es eingeschaltet hat.
     */
    public function isRequired(): bool
    {
        return (bool) Setting::current()->two_factor_required;
    }

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && $user->two_factor_method !== null;
    }

    /**
     * @return array<string, string>
     */
    public static function methodLabels(): array
    {
        return [
            self::METHOD_APP => 'Authenticator-App',
            self::METHOD_EMAIL => 'Code per E-Mail',
        ];
    }

    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(
            fn (string $chunk) => self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))],
            str_split($bits, 5)
        ));
    }

    /**
     * Sechsstelliger TOTP-Code für ein 30-Sekunden-Zeitfenster.
     */
    public function totp(string $secret, int $timeStep): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0, $timeStep), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Prüft einen App-Code (±30 Sekunden Toleranz). Ein Code gilt nur einmal.
     */
    public function verifyTotp(string $secret, string $code, ?int $userId = null): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== 6) {
            return false;
        }

        $currentStep = intdiv(now()->getTimestamp(), self::PERIOD_SECONDS);

        foreach ([-1, 0, 1] as $drift) {
            $step = $currentStep + $drift;

            if (! hash_equals($this->totp($secret, $step), $code)) {
                continue;
            }

            if ($userId === null) {
                return true;
            }

            // Bereits verwendete Codes nicht erneut annehmen.
            return Cache::add($this->cacheKey('totp-used', $userId.':'.$step), true, now()->addMinutes(3));
        }

        return false;
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        $issuer = 'VEMA '.Setting::current()->name;
        $label = rawurlencode($issuer.':'.$user->username);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => self::PERIOD_SECONDS,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function qrCodeSvg(string $content): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($content);
    }

    /**
     * Schickt einen neuen sechsstelligen Code an die Login-E-Mail.
     */
    public function sendEmailCode(User $user): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey('email-code', (string) $user->id), hash('sha256', $code), now()->addMinutes(self::EMAIL_CODE_MINUTES));

        try {
            Mail::to($user->email)->send(new TwoFactorCodeMail($user, $code));
        } catch (\Throwable $exception) {
            Log::error('Zwei-Faktor-Code konnte nicht per E-Mail versendet werden.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function verifyEmailCode(User $user, string $code): bool
    {
        $key = $this->cacheKey('email-code', (string) $user->id);
        $expected = Cache::get($key);
        $code = preg_replace('/\D/', '', $code);

        if (! is_string($expected) || strlen($code) !== 6 || ! hash_equals($expected, hash('sha256', $code))) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    /**
     * Code bei der Anmeldung: je nach Methode App- oder E-Mail-Code, sonst
     * ein Wiederherstellungscode.
     */
    public function verifyLoginCode(User $user, string $code): bool
    {
        $valid = match ($user->two_factor_method) {
            self::METHOD_APP => $this->verifyTotp((string) $user->two_factor_secret, $code, $user->id),
            self::METHOD_EMAIL => $this->verifyEmailCode($user, $code),
            default => false,
        };

        return $valid || $this->useRecoveryCode($user, $code);
    }

    /**
     * Schaltet die gewählte Methode scharf und liefert neue Wiederherstellungscodes.
     *
     * @return list<string>
     */
    public function enable(User $user, string $method, ?string $secret): array
    {
        $user->forceFill([
            'two_factor_method' => $method,
            'two_factor_secret' => $method === self::METHOD_APP ? $secret : null,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $this->regenerateRecoveryCodes($user);
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code) => hash('sha256', $code), $codes),
        ])->save();

        return $codes;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * Ein Wiederherstellungscode gilt genau einmal.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hash = hash('sha256', Str::lower(trim($code)));
        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search($hash, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    /**
     * Entfernt die Zwei-Faktor-Anmeldung; vertraute Geräte verlieren ihre Gültigkeit.
     */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function trustDevice(User $user): void
    {
        $expiresAt = now()->addDays(self::TRUST_DAYS)->getTimestamp();

        Cookie::queue(
            $this->trustCookieName($user),
            $expiresAt.'|'.$this->trustSignature($user, $expiresAt),
            self::TRUST_DAYS * 24 * 60
        );
    }

    public function isTrustedDevice(Request $request, User $user): bool
    {
        $value = (string) $request->cookie($this->trustCookieName($user));

        if (! str_contains($value, '|')) {
            return false;
        }

        [$expiresAt, $signature] = explode('|', $value, 2);

        return ctype_digit($expiresAt)
            && (int) $expiresAt > now()->getTimestamp()
            && hash_equals($this->trustSignature($user, (int) $expiresAt), $signature);
    }

    private function trustCookieName(User $user): string
    {
        return 'vema_2fa_trusted_'.$user->id;
    }

    /**
     * Gebunden an Benutzer, Verein und Einrichtung – nach einem Zurücksetzen
     * der Zwei-Faktor-Anmeldung sind alle vertrauten Geräte ungültig.
     */
    private function trustSignature(User $user, int $expiresAt): string
    {
        return hash_hmac('sha256', implode('|', [
            $this->tenantKey(),
            $user->id,
            $expiresAt,
            $user->two_factor_confirmed_at?->getTimestamp(),
        ]), (string) config('app.key'));
    }

    private function cacheKey(string $purpose, string $suffix): string
    {
        return 'two-factor:'.$purpose.':'.$this->tenantKey().':'.$suffix;
    }

    private function tenantKey(): string
    {
        return (string) (app(TenantManager::class)->current()?->getKey() ?? 'single');
    }

    private function base32Decode(string $secret): string
    {
        $bits = '';

        foreach (str_split(strtoupper($secret)) as $character) {
            $position = strpos(self::BASE32_ALPHABET, $character);

            if ($position !== false) {
                $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }
        }

        return implode('', array_map(
            fn (string $byte) => chr(bindec($byte)),
            array_filter(str_split($bits, 8), fn (string $byte) => strlen($byte) === 8)
        ));
    }
}
