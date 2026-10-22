<p>
    Sehr geehrte Damen und Herren,
</p>

@if($reminder->level === 1)
    <p>
        zu unserer Rechnung {{ $invoice->invoice_number }} über
        {{ number_format((float) $invoice->total_gross, 2, ',', '.') }} € konnten wir bisher keinen
        Zahlungseingang feststellen. Wir bitten Sie freundlich, den Betrag zu überweisen.
    </p>
@elseif($reminder->level === 2)
    <p>
        trotz unserer Zahlungserinnerung ist die Rechnung {{ $invoice->invoice_number }} über
        {{ number_format((float) $invoice->total_gross, 2, ',', '.') }} € weiterhin offen.
        Wir ersuchen Sie, den Betrag in den nächsten Tagen zu überweisen.
    </p>
@else
    <p>
        die Rechnung {{ $invoice->invoice_number }} über
        {{ number_format((float) $invoice->total_gross, 2, ',', '.') }} € ist trotz mehrfacher
        Aufforderung weiterhin offen. Wir fordern Sie letztmalig zur Zahlung auf.
    </p>
@endif

<p>
    Im Anhang finden Sie das Schreiben ({{ $reminder->level_label }}) sowie eine Kopie der Rechnung.
    Sollten Sie bereits bezahlt haben, betrachten Sie diese Nachricht bitte als gegenstandslos.
</p>

<p>
    Mit freundlichen Grüßen<br>
    {{ \App\Models\Setting::current()->name }}
</p>

@include('emails.partials.signature')
