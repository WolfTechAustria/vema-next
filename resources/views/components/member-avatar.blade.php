@props([
    'url' => null,
    'initials' => '',
    'name' => '',
    'size' => 'h-16 w-16 text-xl',
])

@if($url)
    <img
        src="{{ $url }}"
        alt="{{ $name ? 'Profilbild von '.$name : 'Profilbild' }}"
        {{ $attributes->merge(['class' => 'shrink-0 rounded-full bg-slate-200 object-cover '.$size]) }}
    >
@else
    <div
        aria-hidden="true"
        {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center rounded-full bg-slate-200 font-bold text-slate-600 '.$size]) }}
    >
        {{ $initials }}
    </div>
@endif
