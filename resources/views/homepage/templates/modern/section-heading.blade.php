@if($asPageTitle ?? false)
    <h1 class="break-words hyphens-auto mb-10 mt-2 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">{{ $heading }}</h1>
@else
    <div class="mb-10">
        <span class="block h-1 w-12 rounded-full bg-brand"></span>
        <h2 class="break-words hyphens-auto mt-4 text-3xl font-extrabold tracking-tight text-slate-900">{{ $heading }}</h2>
    </div>
@endif
