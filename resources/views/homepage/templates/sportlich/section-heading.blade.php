@if($asPageTitle ?? false)
    <h1 class="break-words hyphens-auto mb-8 mt-2 text-3xl font-black uppercase italic tracking-tight text-zinc-950 sm:text-5xl">
        <span class="border-b-8 border-brand">{{ $heading }}</span>
    </h1>
@else
    <h2 class="break-words hyphens-auto mb-8 inline-block -skew-x-12 bg-zinc-950 px-5 py-2 text-2xl font-black uppercase text-white">
        <span class="block skew-x-12">{{ $heading }}</span>
    </h2>
@endif
