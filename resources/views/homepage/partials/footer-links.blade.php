<div class="flex flex-wrap items-center gap-x-5 gap-y-2">
    <span>© {{ now()->year }} {{ $club->name }}</span>

    @foreach($footerPages as $footerPage)
        <a href="{{ route('homepage.page', $footerPage->slug) }}" class="hover:underline">{{ $footerPage->title }}</a>
    @endforeach

    @if($settings->contact_visible)
        <a href="{{ route('homepage.contact') }}" class="hover:underline">Kontakt</a>
    @endif
</div>
