@php
    $status = $swap->status;
    $title = match ($status) {
        \App\Enums\DutySwapStatus::Completed => $swap->isSwap() ? 'Dienste getauscht' : 'Dienst übergeben',
        \App\Enums\DutySwapStatus::AwaitingApproval => 'Anfrage angenommen',
        \App\Enums\DutySwapStatus::Declined => 'Anfrage abgelehnt',
        \App\Enums\DutySwapStatus::Rejected => 'Tausch nicht freigegeben',
        default => 'Diensttausch',
    };
    $person = $forAcceptor ? $swap->acceptedBy : $swap->requester;
    $other = $forAcceptor ? $swap->requester : ($swap->acceptedBy ?? $swap->targetMember);
@endphp

@include('emails.member-application.box-start', ['title' => $title])

<p style="margin: 0 0 12px 0;">
    Hallo {{ $person?->name }},
</p>

<p style="margin: 0 0 12px 0;">
    @switch($status)
        @case(\App\Enums\DutySwapStatus::Completed)
            @if($swap->isSwap())
                der Tausch ist erledigt: {{ $swap->acceptedBy?->full_name }} übernimmt <strong>{{ $offered }}</strong>,
                {{ $swap->requester->full_name }} übernimmt <strong>{{ $wanted }}</strong>.
            @else
                {{ $swap->acceptedBy?->full_name }} übernimmt den Dienst <strong>{{ $offered }}</strong>.
            @endif
            Der Dienstplan wurde angepasst.
            @break

        @case(\App\Enums\DutySwapStatus::AwaitingApproval)
            {{ $other?->full_name }} hat deine Anfrage zu <strong>{{ $offered }}</strong> angenommen.
            Der Tausch muss noch vom Verein freigegeben werden – du bekommst dann eine weitere Nachricht.
            Bis dahin bleibt dein Dienst bestehen.
            @break

        @case(\App\Enums\DutySwapStatus::Declined)
            {{ $other?->full_name }} hat deine Anfrage zu <strong>{{ $offered }}</strong> abgelehnt.
            Dein Dienst bleibt bestehen.
            @break

        @case(\App\Enums\DutySwapStatus::Rejected)
            der Verein hat den Tausch zu <strong>{{ $offered }}</strong> nicht freigegeben.
            Die Dienste bleiben wie bisher eingeteilt.
            @break
    @endswitch
</p>

@include('emails.member-application.button', ['url' => $portalUrl, 'label' => 'Zu meinen Diensten'])

@include('emails.member-application.box-end')
