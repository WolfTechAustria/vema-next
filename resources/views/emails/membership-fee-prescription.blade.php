@if($type === 'reminder')

    <p>Hallo {{ $memberName }},</p>

    <p>
        wir möchten dich daran erinnern, dass dein Mitgliedsbeitrag
        für das Jahr {{ $year }} noch offen ist.
    </p>

    <p>
        Im Anhang findest du die
        {{ $reminderLevel ?? 1 }}. Erinnerung
        zu deinem Mitgliedsbeitrag.
    </p>

    <p>
        Falls du den Beitrag inzwischen bereits überwiesen hast,
        betrachte diese Nachricht bitte als gegenstandslos.
    </p>

@else

    <p>Hallo {{ $memberName }},</p>

    <p>
        im Anhang findest du deine Beitragsvorschreibung
        für das Jahr {{ $year }}.
    </p>

@endif

<p>
    Mit freundlichen Grüßen<br>
    Schützengilde Angerberg
</p>
