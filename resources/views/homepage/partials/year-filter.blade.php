@if($years->count() > 1)
    <nav class="mb-7 flex flex-wrap gap-2" aria-label="Nach Jahr filtern">
        <a
            href="{{ route('homepage.posts.index') }}"
            @if($selectedYear === null) aria-current="page" @endif
            class="rounded-full px-3.5 py-1.5 text-sm font-semibold {{ $selectedYear === null ? 'bg-brand text-white' : 'bg-black/5 hover:bg-black/10' }}"
        >Alle</a>

        @foreach($years as $filterYear)
            <a
                href="{{ route('homepage.posts.index', ['jahr' => $filterYear]) }}"
                @if($selectedYear === $filterYear) aria-current="page" @endif
                class="rounded-full px-3.5 py-1.5 text-sm font-semibold {{ $selectedYear === $filterYear ? 'bg-brand text-white' : 'bg-black/5 hover:bg-black/10' }}"
            >{{ $filterYear }}</a>
        @endforeach
    </nav>
@endif
