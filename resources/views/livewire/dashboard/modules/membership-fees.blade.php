@php
    $stats = $data['stats'];
    $actions = $data['actions'];
@endphp

@if(! $stats['year'])

    <p class="text-sm text-slate-500">Noch kein Beitragsjahr angelegt.</p>

@else

    <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Beitragsjahr {{ $stats['year'] }}</p>

        <a href="{{ route('membership-fees.index') }}" wire:navigate class="text-sm font-medium text-slate-700 hover:text-slate-900">
            Zu den Mitgliedsbeiträgen →
        </a>
    </div>

    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
        <a href="{{ route('membership-fees.index', ['statusFilter' => 'open']) }}" class="rounded-lg border border-slate-200 p-4 hover:border-slate-300">
            <div class="text-sm text-slate-500">Offen</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $stats['open'] }}</div>
        </a>

        <a href="{{ route('membership-fees.index', ['statusFilter' => 'paid']) }}" class="rounded-lg border border-slate-200 p-4 hover:border-slate-300">
            <div class="text-sm text-slate-500">Bezahlt</div>
            <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ $stats['paid'] }}</div>
        </a>

        <a href="{{ route('membership-fees.index', ['statusFilter' => 'exempt']) }}" class="rounded-lg border border-slate-200 p-4 hover:border-slate-300">
            <div class="text-sm text-slate-500">Befreit</div>
            <div class="mt-1 text-2xl font-semibold text-slate-700">{{ $stats['exempt'] }}</div>
        </a>

        <a href="{{ route('membership-fees.index', ['reminderFilter' => 'due']) }}" class="rounded-lg border border-amber-200 bg-amber-50 p-4 hover:border-amber-300">
            <div class="text-sm text-amber-700">Erinnerung fällig</div>
            <div class="mt-1 text-2xl font-semibold text-amber-800">{{ $stats['reminder_due'] }}</div>
        </a>

        <a href="{{ route('membership-fees.index', ['reminderFilter' => 'sent']) }}" class="rounded-lg border border-blue-200 bg-blue-50 p-4 hover:border-blue-300">
            <div class="text-sm text-blue-700">Bereits erinnert</div>
            <div class="mt-1 text-2xl font-semibold text-blue-800">{{ $stats['reminded'] }}</div>
        </a>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <div class="rounded-lg bg-slate-50 p-4">
            <div class="text-sm text-slate-500">Offener Betrag</div>
            <div class="mt-1 text-2xl font-semibold text-red-700">{{ number_format($stats['open_amount'], 2, ',', '.') }} €</div>
        </div>

        <div class="rounded-lg bg-slate-50 p-4">
            <div class="text-sm text-slate-500">Bereits bezahlt</div>
            <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ number_format($stats['paid_amount'], 2, ',', '.') }} €</div>
        </div>
    </div>

    @if($actions['reminder_due'] > 0 || $actions['open_without_email'] > 0)
        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            @if($actions['reminder_due'] > 0)
                <a href="{{ route('membership-fees.index', ['reminderFilter' => 'due']) }}" class="font-medium text-amber-700 hover:text-amber-900">
                    {{ $actions['reminder_due'] }} fällige Erinnerung(en) prüfen und versenden →
                </a>
            @endif

            @if($actions['open_without_email'] > 0)
                <a href="{{ route('membership-fees.index', ['statusFilter' => 'open', 'emailFilter' => 'without_email']) }}" class="font-medium text-slate-700 hover:text-slate-900">
                    {{ $actions['open_without_email'] }} offene Beiträge ohne E-Mail für Postversand →
                </a>
            @endif
        </div>
    @endif

@endif
