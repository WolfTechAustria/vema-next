@php
    $clubSettings = \App\Models\Setting::current();
@endphp

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    border="0"
    style="
        margin-top: 32px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
        font-family: Arial, Helvetica, sans-serif;
    "
>
    <tr>
        <td
            style="
                width: 140px;
                padding-right: 24px;
                vertical-align: middle;
            "
        >
            <img
                src="https://vema.sg-angerberg.at/images/logo.png"
                alt="{{ $clubSettings->name }}"
                width="120"
                style="
                    display: block;
                    max-width: 120px;
                    height: auto;
                "
            >
        </td>

        <td
            style="
                padding-left: 24px;
                border-left: 1px solid #e5e7eb;
                vertical-align: middle;
                font-size: 13px;
                line-height: 1.6;
                color: #4b5563;
            "
        >
            <strong style="color: #111827;">
                {{ $clubSettings->name }}
            </strong><br>

            @if($clubSettings->street)
                {{ $clubSettings->street }}<br>
            @endif

            @if($clubSettings->zip || $clubSettings->city)
                {{ trim($clubSettings->zip . ' ' . $clubSettings->city) }}<br>
            @endif

            Österreich<br><br>

            @if($clubSettings->email)
                <a
                    href="mailto:{{ $clubSettings->email }}"
                    style="
                        color: #4b5563;
                        text-decoration: none;
                    "
                >
                    {{ $clubSettings->email }}
                </a><br>
            @endif

            @if($clubSettings->website)
                <a
                    href="https://{{ $clubSettings->website }}"
                    style="
                        color: #4b5563;
                        text-decoration: none;
                    "
                >
                    {{ $clubSettings->website }}
                </a>
            @endif
        </td>
    </tr>
</table>
