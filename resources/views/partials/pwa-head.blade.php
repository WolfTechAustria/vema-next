{{--
    Installierbare Web-App (PWA): Manifest und App-Meta-Angaben.
    $forMembers startet die installierte App im Mitgliederbereich.
--}}
<link rel="manifest" href="{{ route('manifest', ($forMembers ?? false) ? ['bereich' => 'mitglied'] : []) }}">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
