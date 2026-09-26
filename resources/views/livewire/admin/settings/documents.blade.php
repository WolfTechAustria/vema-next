<div class="space-y-6">

    @include('partials.flash-messages')

    @include('partials.admin-settings-nav')

    <div>
        <h2 class="text-2xl font-bold tracking-tight">
            Briefpapier & E-Mail
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Logo, Briefpapier und Unterschriften für Rundschreiben, Beitragsvorschreibungen und Rechnungen sowie Absender und Postfach für E-Mails.
        </p>
    </div>

    {{-- Dateien --}}
    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <h3 class="mb-1 font-semibold text-slate-900">Logo & Briefpapier</h3>

        <p class="mb-4 text-sm text-slate-500">
            Das Logo erscheint auf den Login-Seiten, in der E-Mail-Signatur und auf Listen. Das Briefpapier (PDF, erste Seite) wird unter jeden Brief gelegt.
        </p>

        @if($isDemoActive)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Du bist gerade im Testmodus. Dateien können nur außerhalb des Testmodus geändert werden.
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2">

            @foreach([
                ['slot' => 'logo', 'property' => 'logoUpload', 'label' => 'Logo', 'accept' => 'image/png,image/jpeg', 'present' => $logoUrl !== null, 'hint' => 'JPG oder PNG, max. 5 MB'],
                ['slot' => 'letterhead', 'property' => 'letterheadUpload', 'label' => 'Briefpapier', 'accept' => 'application/pdf', 'present' => $hasLetterhead, 'hint' => 'PDF, max. 10 MB'],
            ] as $file)
                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="text-sm font-medium text-slate-700">{{ $file['label'] }}</p>

                    <div class="mt-3 flex h-24 items-center justify-center rounded-md bg-slate-50">
                        @if($file['slot'] === 'logo' && $logoUrl)
                            <img src="{{ $logoUrl }}" alt="Logo" class="max-h-20 w-auto">
                        @elseif($file['present'])
                            <span class="text-sm font-medium text-emerald-700">✓ hinterlegt</span>
                        @else
                            <span class="text-sm text-slate-400">nicht hinterlegt</span>
                        @endif
                    </div>

                    @unless($isDemoActive)
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <label class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <span wire:loading.remove wire:target="{{ $file['property'] }}">{{ $file['present'] ? 'Ersetzen' : 'Hochladen' }}</span>
                                <span wire:loading wire:target="{{ $file['property'] }}">Wird hochgeladen …</span>
                                <input type="file" wire:model="{{ $file['property'] }}" accept="{{ $file['accept'] }}" class="sr-only">
                            </label>

                            @if($file['present'])
                                <button
                                    type="button"
                                    wire:click="removeFile('{{ $file['slot'] }}')"
                                    wire:confirm="{{ $file['label'] }} wirklich entfernen?"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                >
                                    Entfernen
                                </button>
                            @endif
                        </div>

                        <p class="mt-2 text-xs text-slate-500">{{ $file['hint'] }}</p>
                    @endunless

                    @error($file['property'])
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

        </div>

    </section>

    {{-- Briefe --}}
    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <h3 class="mb-1 font-semibold text-slate-900">Briefe</h3>

        <p class="mb-4 text-sm text-slate-500">
            Ort in der Datumszeile und Unterschriften unter Rundschreiben und Beitragsvorschreibungen. Unterschriften ohne Funktion und Name werden weggelassen.
        </p>

        <div class="grid gap-4 md:grid-cols-2">

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Ort in der Datumszeile</label>
                <input type="text" wire:model="letter_place" placeholder="leer = Ort aus den Vereinsdaten" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('letter_place')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @foreach([1, 2] as $number)
                <div class="rounded-lg border border-slate-200 p-4">
                    <p class="mb-3 text-sm font-semibold text-slate-900">Unterschrift {{ $number }}</p>

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Funktion</label>
                            <input type="text" wire:model="signatory_{{ $number }}_title" placeholder="z. B. Der Obmann" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                            <input type="text" wire:model="signatory_{{ $number }}_name" placeholder="z. B. MUSTER Max" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        </div>

                        <div>
                            <p class="mb-1 text-sm font-medium text-slate-700">Unterschrift (Bild)</p>

                            <div class="flex flex-wrap items-center gap-2">
                                @if($hasSignature[$number])
                                    <span class="text-sm font-medium text-emerald-700">✓ hinterlegt</span>
                                @else
                                    <span class="text-sm text-slate-400">nicht hinterlegt</span>
                                @endif

                                @unless($isDemoActive)
                                    <label class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                        <span wire:loading.remove wire:target="signature{{ $number }}Upload">{{ $hasSignature[$number] ? 'Ersetzen' : 'Hochladen' }}</span>
                                        <span wire:loading wire:target="signature{{ $number }}Upload">Wird hochgeladen …</span>
                                        <input type="file" wire:model="signature{{ $number }}Upload" accept="image/png,image/jpeg" class="sr-only">
                                    </label>

                                    @if($hasSignature[$number])
                                        <button
                                            type="button"
                                            wire:click="removeFile('signature_{{ $number }}')"
                                            wire:confirm="Unterschrift {{ $number }} wirklich entfernen?"
                                            class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50"
                                        >
                                            Entfernen
                                        </button>
                                    @endif
                                @endunless
                            </div>

                            @error('signature'.$number.'Upload')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            @endforeach

        </div>

        <div class="mt-6">
            <button
                type="button"
                wire:click="saveLetter"
                wire:loading.attr="disabled"
                wire:target="saveLetter"
                class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
            >
                Speichern
            </button>
        </div>

    </section>

    {{-- E-Mail --}}
    <section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <h3 class="mb-1 font-semibold text-slate-900">E-Mail</h3>

        <p class="mb-4 text-sm text-slate-500">
            Leere Felder verwenden die Konfiguration des Servers.
        </p>

        <div class="grid gap-4 md:grid-cols-2">

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Absender-Adresse</label>
                <input type="email" wire:model="mail_from_address" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('mail_from_address')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Absender-Name</label>
                <input type="text" wire:model="mail_from_name" placeholder="leer = Vereinsname" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Empfängergruppe für Geburtstags-Benachrichtigungen</label>
                <select wire:model="birthday_recipient_group" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="">Standard ({{ $defaultBirthdayGroup }})</option>
                    @foreach($recipientGroups as $groupName)
                        <option value="{{ $groupName }}">{{ $groupName }}</option>
                    @endforeach
                </select>
                @error('birthday_recipient_group')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

        </div>

        <h4 class="mb-1 mt-6 text-sm font-semibold text-slate-900">Gesendet-Ordner (IMAP)</h4>

        <p class="mb-4 text-sm text-slate-500">
            Versendete Mails werden zusätzlich in diesem Postfach abgelegt. Ohne Server gilt das Postfach aus der Server-Konfiguration.
        </p>

        <div class="grid gap-4 md:grid-cols-2">

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Server</label>
                <input type="text" wire:model="imap_host" placeholder="z. B. mail.example.at" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('imap_host')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Port</label>
                    <input type="text" inputmode="numeric" wire:model="imap_port" placeholder="993" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('imap_port')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Verschlüsselung</label>
                    <select wire:model="imap_encryption" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="ssl">SSL</option>
                        <option value="tls">TLS</option>
                        <option value="starttls">STARTTLS</option>
                        <option value="notls">keine</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Benutzername</label>
                <input type="text" wire:model="imap_username" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('imap_username')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Passwort</label>
                <input
                    type="password"
                    wire:model="imap_password"
                    autocomplete="new-password"
                    placeholder="{{ $hasStoredImapPassword ? 'gespeichert – leer lassen zum Behalten' : '' }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Gesendet-Ordner</label>
                <input type="text" wire:model="imap_sent_folder" placeholder="INBOX.Sent" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-3">
                    <input type="checkbox" wire:model="imap_validate_cert" class="h-4 w-4 rounded border-slate-300">
                    <span class="text-sm font-medium text-slate-700">Zertifikat prüfen</span>
                </label>
            </div>

        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button
                type="button"
                wire:click="saveMail"
                wire:loading.attr="disabled"
                wire:target="saveMail"
                class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
            >
                Speichern
            </button>

            <button
                type="button"
                wire:click="testImap"
                wire:loading.attr="disabled"
                wire:target="testImap"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="testImap">Gespeicherte Verbindung testen</span>
                <span wire:loading wire:target="testImap">Wird getestet …</span>
            </button>
        </div>

    </section>

</div>
