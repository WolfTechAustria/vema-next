@include('emails.member-application.box-start', ['title' => $awaitingApproval ? 'Diensttausch wartet auf Freigabe' : ($swap->isSwap() ? 'Dienste getauscht' : 'Dienst übergeben')])

<p style="margin: 0 0 12px 0;">
    @if($swap->isSwap())
        {{ $swap->requester->full_name }} und {{ $swap->acceptedBy?->full_name }} tauschen ihre Dienste:
    @else
        {{ $swap->requester->full_name }} gibt einen Dienst an {{ $swap->acceptedBy?->full_name }} ab:
    @endif
</p>

<ul style="margin: 0 0 12px 0; padding-left: 20px;">
    <li>{{ $offered }}: {{ $swap->requester->full_name }} → {{ $swap->acceptedBy?->full_name }}</li>
    @if($wanted)
        <li>{{ $wanted }}: {{ $swap->acceptedBy?->full_name }} → {{ $swap->requester->full_name }}</li>
    @endif
</ul>

<p style="margin: 0 0 12px 0;">
    @if($awaitingApproval)
        Der Tausch wird erst nach der Freigabe im Dienstplan übernommen.
    @else
        Der Dienstplan wurde bereits angepasst.
    @endif
</p>

@include('emails.member-application.button', ['url' => $swapsUrl, 'label' => $awaitingApproval ? 'Tausch prüfen' : 'Diensttausche ansehen'])

@include('emails.member-application.box-end')
