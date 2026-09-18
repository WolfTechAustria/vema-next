<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Empfängergruppe für den Geburtstags-Reminder
    |--------------------------------------------------------------------------
    |
    | Name der bestehenden RecipientGroup (siehe Rundschreiben-Modul), deren
    | Mitglieder & externe Kontakte die E-Mail-Benachrichtigung erhalten,
    | sobald ein aktives Mitglied einen runden oder halbrunden Geburtstag hat.
    |
    */
    'recipient_group' => env('BIRTHDAY_REMINDER_RECIPIENT_GROUP', 'Vorstand'),

];
