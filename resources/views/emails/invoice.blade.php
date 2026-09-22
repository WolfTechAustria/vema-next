<p>
    Sehr geehrte Damen und Herren,
</p>

<p>
    im Anhang erhalten Sie die Rechnung {{ $invoice->invoice_number }}
    @if($invoice->purpose)
        für {{ $invoice->purpose }}
    @endif
    über {{ number_format((float) $invoice->total_gross, 2, ',', '.') }} €.
</p>

@if($invoice->due_date)
    <p>
        Wir bitten um Überweisung bis zum {{ $invoice->due_date->format('d.m.Y') }}.
    </p>
@endif

<p>
    Mit freundlichen Grüßen<br>
    {{ \App\Models\Setting::current()->name }}
</p>

@include('emails.partials.signature')
