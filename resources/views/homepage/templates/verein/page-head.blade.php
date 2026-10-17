{{-- Farbiger Seitenkopf: Seitenname klein, darunter die eigene Überschrift. --}}
<section class="border-b-4 border-accent bg-[linear-gradient(120deg,var(--color-brand-deep),var(--color-brand-dark))] py-[clamp(40px,7vw,64px)]">
    <div class="mx-auto max-w-[1160px] px-4">
        <p class="text-[13px] font-bold uppercase tracking-[.14em] text-[color-mix(in_oklab,var(--color-accent)_25%,white)]">{{ $eyebrow }}</p>
        <h1 class="mt-1.5 break-words hyphens-auto text-[clamp(2rem,5vw,3rem)] font-bold leading-[1.2] tracking-[-.01em] text-white">{{ $heading ?: $eyebrow }}</h1>
    </div>
</section>
