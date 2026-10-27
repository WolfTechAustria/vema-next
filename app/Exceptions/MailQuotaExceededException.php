<?php

namespace App\Exceptions;

use App\Enums\Limit;
use RuntimeException;

/**
 * Monatliches E-Mail-Kontingent des Vereins reicht nicht für den Versand.
 */
class MailQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $requested,
    ) {
        parent::__construct(
            'Das E-Mail-Kontingent eures Pakets ('.Limit::EmailsPerMonth->format($limit).' pro Monat) reicht nicht: '
            .($remaining > 0 ? 'noch '.Limit::EmailsPerMonth->format($remaining).' frei, benötigt '.Limit::EmailsPerMonth->format($requested).'.' : 'es ist aufgebraucht.')
            .' Ab '.now()->addMonthNoOverflow()->startOfMonth()->format('d.m.Y').' steht es wieder voll zur Verfügung — oder ihr bucht ein größeres Paket.'
        );
    }
}
