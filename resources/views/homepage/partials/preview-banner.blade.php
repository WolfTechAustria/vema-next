@if($isPreview || ! $settings->enabled)
    <div class="bg-amber-400 px-4 py-2 text-center text-sm font-medium text-amber-950">
        @if($isPreview)
            Vorschau – dieser Beitrag ist noch nicht öffentlich sichtbar.
        @else
            Vorschau – die Homepage ist noch nicht veröffentlicht und nur für angemeldete Homepage-Verantwortliche sichtbar.
        @endif
    </div>
@endif
