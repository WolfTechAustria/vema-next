@php
    $iconColors = [
        'pdf' => 'bg-red-50 text-red-600',
        'image' => 'bg-sky-50 text-sky-600',
        'text' => 'bg-blue-50 text-blue-700',
        'sheet' => 'bg-emerald-50 text-emerald-700',
        'other' => 'bg-slate-100 text-slate-500',
    ];

    $iconLabels = [
        'pdf' => 'PDF',
        'image' => 'BILD',
        'text' => 'DOC',
        'sheet' => 'XLS',
        'other' => 'DATEI',
    ];
@endphp

<span class="relative inline-flex shrink-0 items-center justify-center rounded-lg {{ $iconColors[$kind] ?? $iconColors['other'] }} {{ $size }}" aria-hidden="true">
    <svg class="h-3/5 w-3/5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
        <path stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" />
        <path stroke-linejoin="round" d="M14 3v5h5" />
    </svg>
    <span class="absolute bottom-0.5 text-[8px] font-bold leading-none tracking-tight">{{ $iconLabels[$kind] ?? $iconLabels['other'] }}</span>
</span>
