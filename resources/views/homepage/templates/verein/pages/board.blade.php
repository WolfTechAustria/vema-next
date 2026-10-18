@extends($templatePath.'.layout')

@section('content')
    @include($templatePath.'.page-head', ['eyebrow' => $pageTitle, 'heading' => $pageHeading])

    @if($settings->show_board_photos)
        <section class="py-[clamp(48px,8vw,80px)]">
            <div class="mx-auto grid max-w-[1160px] gap-6 px-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($boardMembers as $boardMember)
                    <div class="flex items-center gap-4 rounded-xl border border-[#dfe6e0] bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,.05),0_4px_16px_rgba(0,0,0,.06)]">
                        @if($boardMember['photoUrl'])
                            <img src="{{ $boardMember['photoUrl'] }}" alt="{{ $boardMember['name'] }}" loading="lazy" class="h-20 w-20 flex-none rounded-full object-cover">
                        @else
                            <span class="flex h-20 w-20 flex-none items-center justify-center rounded-full bg-brand-soft text-xl font-bold text-brand-dark">{{ $boardMember['initials'] }}</span>
                        @endif

                        <span>
                            <strong class="block text-lg text-brand-deep">{{ $boardMember['name'] }}</strong>
                            <span class="block text-[15px] text-[#5b665e]">{{ $boardMember['label'] }}</span>
                        </span>
                    </div>
                @empty
                    <p class="text-[#5b665e]">Derzeit sind keine Vorstandsfunktionen eingetragen.</p>
                @endforelse
            </div>
        </section>
    @else
        <section class="py-[clamp(48px,8vw,80px)]">
            <div class="mx-auto max-w-[1160px] px-4">
                @include('homepage.sections.board', ['section' => new \App\Models\HomepageSection(['type' => \App\Enums\HomepageSectionType::Board])])
            </div>
        </section>
    @endif
@endsection
