<?php

namespace App\Rules;

use App\Services\Sepa\SepaValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * IBAN eines SEPA-Landes mit gültiger Prüfziffer.
 */
class ValidIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(SepaValidator::class)->isValidIban(is_string($value) ? $value : null)) {
            $fail('Bitte eine gültige IBAN eingeben (z. B. AT61 1904 3002 3457 3201).');
        }
    }
}
