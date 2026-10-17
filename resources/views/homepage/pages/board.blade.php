@extends($templatePath.'.layout')

@section('content')
    @include($templatePath.'.section-heading', ['heading' => 'Vorstand', 'asPageTitle' => true])

    @if($boardMembers->isEmpty())
        <p class="opacity-70">Derzeit sind keine Vorstandsfunktionen eingetragen.</p>
    @else
        <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($boardMembers as $boardMember)
                <li class="flex items-center gap-4 rounded-xl border border-black/10 p-5">
                    @if($boardMember['photoUrl'])
                        <img src="{{ $boardMember['photoUrl'] }}" alt="{{ $boardMember['name'] }}" loading="lazy" class="h-20 w-20 flex-none rounded-full object-cover">
                    @else
                        <span class="flex h-20 w-20 flex-none items-center justify-center rounded-full bg-brand text-xl font-bold text-white">
                            {{ $boardMember['initials'] }}
                        </span>
                    @endif

                    <span>
                        <span class="block text-lg font-semibold">{{ $boardMember['name'] }}</span>
                        <span class="block text-sm opacity-70">{{ implode(', ', $boardMember['functions']) }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
