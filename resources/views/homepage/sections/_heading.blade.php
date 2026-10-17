@if($section->heading)
    <h2 class="mb-4 break-words hyphens-auto text-[clamp(1.4rem,3vw,1.9rem)] font-bold leading-tight text-brand-deep {{ $center ?? false ? 'text-center' : '' }}">{{ $section->heading }}</h2>
@endif
