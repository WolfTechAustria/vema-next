@include('emails.member-application.box-start', ['title' => $news->title])

<div style="margin: 0 0 16px 0;">
    {{ $news->bodyHtml() }}
</div>

@include('emails.member-application.button', ['url' => $newsUrl, 'label' => 'In VEMA ansehen'])

<p style="margin: 0; font-size: 13px; color: #64748b;">
    Alle Neuigkeiten finden Administratoren in VEMA unter „Neuigkeiten“.
</p>

@include('emails.member-application.box-end')
