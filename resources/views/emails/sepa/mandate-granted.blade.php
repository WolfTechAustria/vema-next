@include('emails.member-application.box-start', ['title' => $mandate->isActive() ? 'Neues SEPA-Lastschriftmandat' : 'SEPA-Lastschriftmandat widerrufen'])

<p style="margin: 0 0 12px 0;">
    @if($mandate->isActive())
        {{ $mandate->member?->full_name }} hat im Mitgliederbereich ein SEPA-Lastschriftmandat erteilt.
    @else
        {{ $mandate->member?->full_name }} hat das SEPA-Lastschriftmandat im Mitgliederbereich widerrufen. Künftige Beiträge können nicht mehr eingezogen werden.
    @endif
</p>

<table cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px 0; font-size: 14px;">
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">Mandatsreferenz</td><td>{{ $mandate->reference }}</td></tr>
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">Kontoinhaber</td><td>{{ $mandate->account_holder }}</td></tr>
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">IBAN</td><td>{{ $mandate->maskedIban() }}</td></tr>
    <tr><td style="padding: 2px 16px 2px 0; color: #64748b;">Erteilt am</td><td>{{ $mandate->signed_on->format('d.m.Y') }}</td></tr>
</table>

@include('emails.member-application.button', ['url' => $memberUrl, 'label' => 'Mitglied in VEMA öffnen'])

@include('emails.member-application.box-end')
