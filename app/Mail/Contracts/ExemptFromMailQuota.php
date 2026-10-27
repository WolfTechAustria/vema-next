<?php

namespace App\Mail\Contracts;

/**
 * Mail zählt nicht zum monatlichen E-Mail-Kontingent des Vereins und wird
 * auch bei aufgebrauchtem Kontingent verschickt (Anmeldung, Passwort,
 * Hinweise an den Vorstand).
 */
interface ExemptFromMailQuota {}
