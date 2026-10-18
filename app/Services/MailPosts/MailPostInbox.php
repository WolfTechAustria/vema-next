<?php

namespace App\Services\MailPosts;

use App\Models\HomepageSetting;

/**
 * Postfach für Homepage-Beiträge per E-Mail.
 */
interface MailPostInbox
{
    /**
     * Ungelesene Nachrichten des eingestellten Ordners.
     *
     * @return iterable<int, IncomingMail>
     */
    public function unread(HomepageSetting $settings): iterable;

    /**
     * Nach der Verarbeitung: als gelesen markieren bzw. in den Verarbeitet-Ordner verschieben.
     */
    public function markProcessed(HomepageSetting $settings, IncomingMail $mail): void;

    /**
     * Verbindung prüfen; liefert die Anzahl ungelesener Nachrichten.
     */
    public function test(HomepageSetting $settings): int;

    public function disconnect(): void;
}
