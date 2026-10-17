@extends($templatePath.'.layout')

@php
    $address = trim(implode(', ', array_filter([$club->street, trim($club->zip.' '.$club->city)])));
    $cards = array_filter([
        'E-Mail' => $club->email ? '<a href="mailto:'.e($club->email).'" class="text-brand hover:text-brand-dark">'.e($club->email).'</a>' : null,
        'Telefon' => $club->phone ? '<a href="tel:'.e(preg_replace('/[^0-9+]/', '', $club->phone)).'" class="text-brand hover:text-brand-dark">'.e($club->phone).'</a>' : null,
        'Adresse' => $address !== '' ? e($club->name).'<br>'.e($address) : null,
        ($settings->footer_info_title ?: 'Info') => $settings->footer_info_text ? nl2br(e($settings->footer_info_text)) : null,
    ]);
@endphp

@section('content')
    @include($templatePath.'.page-head', ['eyebrow' => $pageTitle, 'heading' => $pageHeading])

    <section class="py-[clamp(48px,8vw,80px)]">
        <div class="mx-auto mb-8 grid max-w-[1160px] gap-5 px-4 sm:grid-cols-2 {{ count($cards) >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
            @foreach($cards as $cardTitle => $cardContent)
                <div class="rounded-xl bg-[#f3f6f3] p-6">
                    <h3 class="mb-2 text-base font-bold uppercase tracking-[.08em] text-[#5b665e]">{{ $cardTitle }}</h3>
                    <p class="font-semibold [overflow-wrap:anywhere]">{!! $cardContent !!}</p>
                </div>
            @endforeach
        </div>

        @if($settings->contact_text)
            <div class="mx-auto max-w-[780px] px-4 text-center">
                <p class="whitespace-pre-line">{{ $settings->contact_text }}</p>
            </div>
        @endif

        @if($club->whatsapp_community_url)
            <div class="mt-6 text-center">
                <a href="{{ $club->whatsapp_community_url }}" target="_blank" rel="noopener" class="inline-block rounded-lg bg-brand px-6 py-3 text-[15px] font-bold text-white hover:bg-brand-dark">Zur WhatsApp-Community</a>
            </div>
        @endif
    </section>
@endsection
