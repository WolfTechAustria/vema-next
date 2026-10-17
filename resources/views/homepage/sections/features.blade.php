@php
    $features = collect($section->value('items', []))->filter(fn (array $item) => filled($item['value'] ?? null) || filled($item['title'] ?? null));
@endphp

@include('homepage.sections._heading', ['center' => true])

<div class="mt-8 grid gap-6 {{ $features->count() >= 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'md:grid-cols-3' }}">
    @foreach($features as $feature)
        <div class="rounded-xl bg-white p-7 text-center shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)]">
            @if(filled($feature['value'] ?? null))
                <strong class="block text-[2.6rem] font-bold leading-none text-brand">{{ $feature['value'] }}</strong>
            @endif

            @if(filled($feature['title'] ?? null))
                <h3 class="mb-1.5 mt-2.5 text-lg font-bold text-brand-deep">{{ $feature['title'] }}</h3>
            @endif

            @if(filled($feature['text'] ?? null))
                <p class="text-[#5b665e]">{{ $feature['text'] }}</p>
            @endif
        </div>
    @endforeach
</div>
