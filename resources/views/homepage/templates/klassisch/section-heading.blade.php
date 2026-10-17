@if($asPageTitle ?? false)
    <h1 class="break-words hyphens-auto mb-8 mt-2 font-serif text-3xl font-bold text-stone-900 sm:text-4xl">{{ $heading }}</h1>
@else
    <h2 class="break-words hyphens-auto mb-8 flex items-center gap-4 font-serif text-2xl font-bold text-stone-900">
        <span class="h-px flex-1 bg-stone-300"></span>
        {{ $heading }}
        <span class="h-px flex-1 bg-stone-300"></span>
    </h2>
@endif
