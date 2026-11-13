@include('emails.member-application.box-start', ['title' => $swap->isSwap() ? 'Tauschanfrage für einen Dienst' : 'Dienst zur Übernahme angeboten'])

<p style="margin: 0 0 12px 0;">
    Hallo {{ $recipient->name }},
</p>

@if($swap->isSwap())
    <p style="margin: 0 0 12px 0;">
        {{ $swap->requester->full_name }} möchte mit dir Dienste tauschen:
    </p>
    <ul style="margin: 0 0 12px 0; padding-left: 20px;">
        <li>Du übernimmst: <strong>{{ $offered }}</strong></li>
        <li>{{ $swap->requester->name }} übernimmt dafür deinen Dienst: <strong>{{ $wanted }}</strong></li>
    </ul>
@else
    <p style="margin: 0 0 12px 0;">
        {{ $swap->requester->full_name }} kann den Dienst <strong>{{ $offered }}</strong> nicht wahrnehmen und sucht
        {{ $swap->isOpenToAll() ? 'jemanden aus dem Dienstplan, der ihn übernimmt. Wer zuerst annimmt, übernimmt den Dienst.' : 'dich als Vertretung.' }}
    </p>
@endif

@if($swap->message)
    <p style="margin: 0 0 12px 0; padding: 10px 14px; background: #f1f5f9; border-radius: 8px;">
        „{{ $swap->message }}“
    </p>
@endif

@if($warnings !== [])
    <p style="margin: 0 0 12px 0; padding: 10px 14px; background: #fef3c7; border-radius: 8px;">
        @foreach($warnings as $warning)
            {{ $warning }}@if(! $loop->last)<br>@endif
        @endforeach
    </p>
@endif

<p style="margin: 0 0 12px 0;">
    Du kannst die Anfrage im Mitgliederbereich unter „Meine Dienste“ annehmen oder ablehnen.
</p>

@include('emails.member-application.button', ['url' => $portalUrl, 'label' => 'Zu meinen Diensten'])

@include('emails.member-application.box-end')
