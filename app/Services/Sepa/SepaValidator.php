<?php

namespace App\Services\Sepa;

/**
 * Prüft IBAN, BIC und Gläubiger-ID (Creditor Identifier) nach den
 * SEPA-Regeln (Länge je Land, Prüfziffer modulo 97).
 */
class SepaValidator
{
    /**
     * IBAN-Längen der SEPA-Länder.
     *
     * @var array<string, int>
     */
    private const IBAN_LENGTHS = [
        'AD' => 24, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28, 'CZ' => 24,
        'DE' => 22, 'DK' => 18, 'EE' => 20, 'ES' => 24, 'FI' => 18, 'FR' => 27, 'GB' => 22,
        'GI' => 23, 'GR' => 27, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IS' => 26, 'IT' => 27,
        'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MT' => 31, 'NL' => 18,
        'NO' => 15, 'PL' => 28, 'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24,
        'SM' => 27, 'VA' => 22,
    ];

    /**
     * Großbuchstaben ohne Leerzeichen: „at61 1904 …“ → „AT611904…“.
     */
    public static function normalize(?string $value): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $value));
    }

    public function isValidIban(?string $iban): bool
    {
        $iban = self::normalize($iban);

        if (! preg_match('/^([A-Z]{2})(\d{2})([A-Z0-9]+)$/', $iban, $matches)) {
            return false;
        }

        $length = self::IBAN_LENGTHS[$matches[1]] ?? null;

        if ($length === null || strlen($iban) !== $length) {
            return false;
        }

        return $this->mod97(substr($iban, 4).substr($iban, 0, 4)) === 1;
    }

    public function isValidBic(?string $bic): bool
    {
        return (bool) preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', self::normalize($bic));
    }

    /**
     * Gläubiger-ID, z. B. „AT61ZZZ01234567890“: Land, Prüfziffern,
     * Geschäftsbereich (wird bei der Prüfung ignoriert), nationale Kennung.
     */
    public function isValidCreditorId(?string $creditorId): bool
    {
        $creditorId = self::normalize($creditorId);

        if (! preg_match('/^([A-Z]{2})(\d{2})([A-Z0-9]{3})([A-Z0-9]{1,28})$/', $creditorId, $matches)) {
            return false;
        }

        return $this->mod97($matches[4].$matches[1].$matches[2]) === 1;
    }

    /**
     * Rest modulo 97, Buchstaben als Zahlen (A = 10 … Z = 35).
     */
    private function mod97(string $value): int
    {
        $numeric = '';

        foreach (str_split($value) as $character) {
            $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
        }

        $remainder = 0;

        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder;
    }
}
