{{--
    Popup (nur Startseite) und Cookie-Hinweis. Beide sind zunächst versteckt;
    resources/js/homepage.js blendet sie ein, sofern der Besucher sie noch
    nicht geschlossen hat (gemerkt im Local Storage, kein Cookie).
--}}
@php
    $showPopup = request()->routeIs('homepage.home') && $settings->popupIsActive();
    $privacyPage = $settings->cookie_banner_enabled && $settings->cookie_banner_page_id
        ? \App\Models\HomepagePage::query()->published()->find($settings->cookie_banner_page_id)
        : null;
@endphp

@if($showPopup)
    @php
        $popupImageUrl = $imageUrls->popupImage($settings);
        $popupButtonUrl = \App\Services\HomepageHosts::link($settings->popup_button_url);
    @endphp

    <div
        class="hp-popup"
        role="dialog"
        aria-modal="true"
        aria-labelledby="hp-popup-title"
        data-hp-popup="{{ $settings->homepageSettingID }}-{{ $settings->popup_version }}"
        data-frequency="{{ $settings->popup_frequency }}"
        hidden
    >
        <div class="hp-popup__dialog">
            <button type="button" class="hp-popup__close" data-hp-popup-close aria-label="Schließen">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>

            @if($popupImageUrl)
                <img src="{{ $popupImageUrl }}" alt="" class="hp-popup__image">
            @endif

            <div class="hp-popup__body">
                @if($settings->popup_title)
                    <h2 id="hp-popup-title" class="hp-popup__title">{{ $settings->popup_title }}</h2>
                @endif

                @if($settings->popup_html)
                    <div class="hp-content hp-popup__text">{!! app(\App\Services\HtmlSanitizer::class)->sanitize($settings->popup_html) !!}</div>
                @endif

                <div class="hp-popup__actions">
                    @if($settings->popup_button_label && $popupButtonUrl)
                        <a href="{{ $popupButtonUrl }}" class="hp-popup__button" data-hp-popup-close>{{ $settings->popup_button_label }}</a>
                    @endif

                    <button type="button" class="hp-popup__dismiss" data-hp-popup-close>Schließen</button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($settings->cookie_banner_enabled)
    <div class="hp-cookie" role="region" aria-label="Hinweis zu Cookies" data-hp-cookie hidden>
        <p class="hp-cookie__text">
            {{ $settings->cookieBannerText() }}
            @if($privacyPage)
                <a href="{{ route('homepage.page', $privacyPage->slug) }}" class="hp-cookie__link">Mehr in der Datenschutzerklärung</a>
            @endif
        </p>

        <button type="button" class="hp-cookie__button" data-hp-cookie-accept>Verstanden</button>
    </div>
@endif
