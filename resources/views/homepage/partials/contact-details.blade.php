{{-- Kontaktdaten aus den Vereinseinstellungen. --}}
<dl class="space-y-4 text-lg">
    <div>
        <dt class="text-sm font-semibold uppercase tracking-wide opacity-60">Verein</dt>
        <dd class="font-medium">{{ $club->name }}</dd>
    </div>

    @if($club->street || $club->city)
        <div>
            <dt class="text-sm font-semibold uppercase tracking-wide opacity-60">Anschrift</dt>
            <dd>
                @if($club->street){{ $club->street }}<br>@endif
                {{ trim($club->zip.' '.$club->city) }}
            </dd>
        </div>
    @endif

    @if($club->email)
        <div>
            <dt class="text-sm font-semibold uppercase tracking-wide opacity-60">E-Mail</dt>
            <dd><a href="mailto:{{ $club->email }}" class="text-brand hover:underline">{{ $club->email }}</a></dd>
        </div>
    @endif

    @if($club->phone)
        <div>
            <dt class="text-sm font-semibold uppercase tracking-wide opacity-60">Telefon</dt>
            <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $club->phone) }}" class="text-brand hover:underline">{{ $club->phone }}</a></dd>
        </div>
    @endif

    @if($club->whatsapp_community_url)
        <div>
            <dt class="text-sm font-semibold uppercase tracking-wide opacity-60">WhatsApp</dt>
            <dd><a href="{{ $club->whatsapp_community_url }}" target="_blank" rel="noopener" class="text-brand hover:underline">Zur WhatsApp-Community</a></dd>
        </div>
    @endif
</dl>
