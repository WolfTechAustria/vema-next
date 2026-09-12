<div class="mx-auto w-full max-w-lg">

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-5">

            <h1 class="text-xl font-semibold text-slate-900">
                Profil auswählen
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Mit deiner E-Mail-Adresse sind mehrere Mitglieder verknüpft.
                Wähle aus, welches Profil du öffnen möchtest.
            </p>

        </div>

        <div class="space-y-3 p-6">

            @foreach($members as $member)

                <button
                    type="button"
                    wire:click="selectProfile({{ $member->memberID }})"
                    wire:loading.attr="disabled"
                    class="flex w-full items-center justify-between rounded-xl border border-slate-200 px-4 py-4 text-left transition hover:border-slate-300 hover:bg-slate-50 disabled:opacity-60"
                >

                    <div>
                        <div class="font-medium text-slate-900">
                            {{ $member->full_name }}
                        </div>

                        @if($member->dateOfBirth)
                            <div class="mt-1 text-sm text-slate-500">
                                Geboren am {{ $member->dateOfBirth->format('d.m.Y') }}
                            </div>
                        @endif
                    </div>

                    <svg
                        class="h-5 w-5 text-slate-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>

                </button>

            @endforeach

        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 text-center text-xs text-slate-500">
            Anmeldung als {{ $account->email }}
        </div>

    </div>

</div>
