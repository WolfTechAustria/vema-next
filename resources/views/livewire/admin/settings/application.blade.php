<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">Online-Anmeldung</h2>

        <p class="mt-1 text-sm text-slate-500">
            Neue Mitglieder füllen die Beitrittserklärung online aus. Anmeldungen erscheinen unter
            <a href="{{ route('members.applications.index') }}" wire:navigate class="underline">Mitglieder → Online-Anmeldungen</a>
            und werden erst nach eurer Freigabe zu Mitgliedern.
        </p>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Aktiv + Link --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="enabled" class="rounded border-slate-300">
                <span class="font-medium text-slate-900">Online-Anmeldung aktivieren</span>
            </label>

            <div class="mt-4" x-data="{ copied: false }">
                <span class="mb-1 block text-sm font-medium text-slate-700">Link zum Formular</span>
                <div class="flex gap-2">
                    <input type="text" readonly value="{{ $formUrl }}" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm" x-ref="url">
                    <button
                        type="button"
                        x-on:click="navigator.clipboard.writeText($refs.url.value); copied = true; setTimeout(() => copied = false, 1500)"
                        class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        x-text="copied ? 'Kopiert' : 'Kopieren'"
                    >Kopieren</button>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Auf eurer Homepage z. B. als Button „Mitglied werden“ verlinken. Auf der Vereinshomepage führt auch die Adresse
                    <code class="rounded bg-slate-100 px-1">/anmeldung</code> hierher.
                </p>
            </div>
        </section>

        {{-- Texte --}}
        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-slate-900">Texte</h3>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Einleitung</label>
                <textarea wire:model="intro" rows="4" placeholder="z. B. Der Mitgliedsbeitrag beträgt derzeit € 40,– pro Jahr …" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <p class="mt-1 text-xs text-slate-500">Steht oben im Formular – etwa Mitgliedsbeitrag und Ablauf.</p>
                @error('intro') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Datenschutzhinweise</label>
                <textarea wire:model="privacyText" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <p class="mt-1 text-xs text-slate-500">Steht unten im Formular und in der Beitrittserklärung (PDF).</p>
                @error('privacyText') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        {{-- Felder --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-slate-900">Felder</h3>
            <p class="mt-1 text-sm text-slate-500">Vorname, Nachname und E-Mail werden immer abgefragt. Das Beitrittsdatum ist der Tag der Anmeldung.</p>

            <div class="mt-4 divide-y divide-slate-100">
                @foreach($fieldLabels as $key => $label)
                    <div wire:key="field-{{ $key }}" class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                        <span class="text-sm text-slate-800">{{ $label }}</span>
                        <div class="flex gap-5 text-sm">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="fields.{{ $key }}.visible" class="rounded border-slate-300">
                                anzeigen
                            </label>
                            <label class="flex items-center gap-2 {{ ($fields[$key]['visible'] ?? false) ? '' : 'opacity-40' }}">
                                <input type="checkbox" wire:model="fields.{{ $key }}.required" @disabled(! ($fields[$key]['visible'] ?? false)) class="rounded border-slate-300">
                                Pflicht
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($memberFieldsEnabled)
                <p class="mt-4 text-sm text-slate-500">
                    Eigene Mitgliederfelder im Formular:
                    <strong>{{ $applicationFields->isEmpty() ? 'keine' : $applicationFields->pluck('name')->implode(', ') }}</strong>
                    – auswählbar unter
                    <a href="{{ route('admin.settings.member-fields') }}" wire:navigate class="underline">Mitgliederfelder</a>.
                </p>
            @endif
        </section>

        {{-- Mitgliedsarten --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Mitgliedsarten</h3>
                <button type="button" wire:click="addMembershipType" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Mitgliedsart</button>
            </div>
            <p class="mt-1 text-sm text-slate-500">Zur Auswahl im Formular, jeweils mit Infotext darunter. Ohne Mitgliedsarten entfällt die Auswahl.</p>

            <div class="mt-4 space-y-4">
                @foreach($membershipTypes as $index => $type)
                    <div wire:key="membership-type-{{ $index }}" class="rounded-lg border border-slate-200 p-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-slate-700">Bezeichnung</label>
                                <input type="text" wire:model="membershipTypes.{{ $index }}.label" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('membershipTypes.'.$index.'.label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Beim Mitglied</label>
                                <select wire:model="membershipTypes.{{ $index }}.kind" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                    @foreach($kinds as $kind)
                                        <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <label class="mb-1 mt-3 block text-sm font-medium text-slate-700">Infotext <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea wire:model="membershipTypes.{{ $index }}.info" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>

                        <div class="mt-2 flex justify-end gap-4 text-sm">
                            <button type="button" wire:click="moveMembershipType({{ $index }}, -1)" class="text-slate-400 hover:text-slate-700" title="Nach oben">↑</button>
                            <button type="button" wire:click="moveMembershipType({{ $index }}, 1)" class="text-slate-400 hover:text-slate-700" title="Nach unten">↓</button>
                            <button type="button" wire:click="removeMembershipType({{ $index }})" class="font-medium text-red-600 hover:text-red-800">Entfernen</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Zustimmungen --}}
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">Zustimmungen</h3>
                <button type="button" wire:click="addConsent" class="text-sm font-medium text-slate-600 hover:text-slate-900">+ Zustimmung</button>
            </div>
            <p class="mt-1 text-sm text-slate-500">Checkboxen unter den Datenschutzhinweisen, z. B. für Datenverarbeitung oder Fotos.</p>

            <div class="mt-4 space-y-3">
                @foreach($consents as $index => $consent)
                    <div wire:key="consent-{{ $index }}" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                        <div class="flex-1">
                            <textarea wire:model="consents.{{ $index }}.text" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                            @error('consents.'.$index.'.text') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center gap-4 text-sm sm:pt-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="consents.{{ $index }}.required" class="rounded border-slate-300">
                                Pflicht
                            </label>
                            <button type="button" wire:click="removeConsent({{ $index }})" class="font-medium text-red-600 hover:text-red-800">Entfernen</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Unterschrift, Spam-Schutz, Benachrichtigung --}}
        <section class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-semibold text-slate-900">Absenden</h3>

            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" wire:model="signature" class="rounded border-slate-300">
                <span><span class="font-medium text-slate-900">Unterschrift verlangen</span> – mit Finger oder Maus im Formular</span>
            </label>

            <div>
                <span class="mb-2 block text-sm font-medium text-slate-700">Spam-Schutz</span>
                <div class="space-y-2">
                    @foreach($spamOptions as $value => $label)
                        @php $needsCaptcha = $value !== \App\Services\MemberApplicationSettings::SPAM_EMAIL; @endphp
                        <label class="flex items-center gap-3 text-sm {{ $needsCaptcha && ! $captchaAvailable ? 'opacity-50' : '' }}">
                            <input type="radio" wire:model="spamProtection" value="{{ $value }}" @disabled($needsCaptcha && ! $captchaAvailable) class="border-slate-300">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    E-Mail-Bestätigung: Die Anmeldung erscheint erst, wenn der Link in der E-Mail angeklickt wurde.
                    Captcha: Sicherheitsprüfung von Cloudflare Turnstile beim Absenden (bitte in den Datenschutzhinweisen erwähnen).
                    @unless($captchaAvailable)
                        <strong>Captcha ist auf diesem Server noch nicht eingerichtet.</strong>
                    @endunless
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Benachrichtigung über neue Anmeldungen an</label>
                <textarea wire:model="notifyEmails" rows="2" placeholder="obmann@verein.at" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"></textarea>
                <p class="mt-1 text-xs text-slate-500">Eine Adresse pro Zeile. Leer: alle Administratoren.</p>
                @error('notifyEmails') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white">Speichern</button>
        </div>

    </form>

</div>
