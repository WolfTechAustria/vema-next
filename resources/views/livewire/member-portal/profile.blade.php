<div class="mx-auto max-w-3xl space-y-6">

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">
                Mein Profil
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Willkommen im Mitgliederbereich.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">

            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    Name
                </div>

                <div class="mt-1 font-medium text-slate-900">
                    {{ $member->full_name }}
                </div>
            </div>

            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    E-Mail
                </div>

                <div class="mt-1 text-slate-700">
                    {{ $account->email }}
                </div>
            </div>

            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    Straße
                </div>

                <div class="mt-1 text-slate-700">
                    {{ $member->street }}
                </div>
            </div>

            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    Ort
                </div>

                <div class="mt-1 text-slate-700">
                    {{ $member->zip }}
                    {{ $member->city?->city }}
                </div>
            </div>

        </div>

    </div>

</div>
