@include('emails.member-application.box-start', ['title' => 'Ankündigung SEPA-Lastschrift'])

<p style="margin: 0 0 12px 0;">
    Hallo {{ $member?->name }},
</p>

<p style="margin: 0 0 12px 0;">
    wir ziehen den Mitgliedsbeitrag {{ $yearName }} wie vereinbart per SEPA-Lastschrift ein:
</p>

<table cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px 0; font-size: 14px;">
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">Betrag</td><td><strong>{{ number_format((float) $item->amount, 2, ',', '.') }} €</strong></td></tr>
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">Einzug am</td><td><strong>{{ $run->collection_date->format('d.m.Y') }}</strong></td></tr>
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">Konto</td><td>{{ $item->mandate?->maskedIban() }}</td></tr>
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">Mandatsreferenz</td><td>{{ $item->mandate?->reference }}</td></tr>
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">Gläubiger-ID</td><td>{{ $creditorId }}</td></tr>
</table>

<p style="margin: 0 0 12px 0;">
    Bitte sorgen Sie für ausreichende Deckung des Kontos. Bei Fragen wenden Sie sich bitte an {{ $clubName }}.
</p>

@include('emails.member-application.box-end')
