<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold tracking-tight">Beiträge per E-Mail</h2>

        <p class="mt-1 text-sm text-slate-500">
            Beiträge einfach per E-Mail einreichen – mit Fotos und PDFs im Anhang. Sie landen als Entwurf unter
            <a href="{{ route('homepage-admin.posts.index', ['filter' => 'draft']) }}" wire:navigate class="underline">Beiträge</a>.
        </p>
    </div>

    @include('partials.flash-messages')

    <div class="grid gap-6 lg:grid-cols-2">

        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold">So geht's</h3>

            @if($settings->mail_posts_enabled && $settings->hasMailPostAccount())
                <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    Senden Sie Ihren Beitrag an
                    <strong class="break-all">{{ $settings->mail_posts_address ?: $settings->mail_posts_username }}</strong>
                </div>
            @else
                <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Noch nicht eingerichtet{{ $isAdmin ? ' – bitte rechts das Postfach eintragen.' : ' – ein Admin muss das Postfach eintragen.' }}
                </div>
            @endif

            <ol class="list-inside list-decimal space-y-2 text-sm text-slate-700">
                <li><strong>Betreff</strong> = Titel des Beitrags.</li>
                <li><strong>Text</strong> der Mail = Inhalt. Leerzeile = neuer Absatz, Zeilen mit „- “ = Aufzählung. Die Signatur und zitierte Antworten werden weggelassen.</li>
                <li><strong>Fotos</strong> (JPG, PNG, WebP) im Anhang kommen in die Bildergalerie – das erste ist das Titelbild. <strong>PDFs</strong> werden Downloads.</li>
                <li>Der Beitrag erscheint als <strong>Entwurf</strong>; Sie bekommen eine Bestätigung. Veröffentlicht wird erst nach Prüfung in VEMA.</li>
            </ol>

            <p class="text-xs text-slate-500">
                Erlaubt sind Absender, deren Adresse zu einem VEMA-Benutzer mit Recht für die Homepage gehört, sowie die von einem Admin eingetragenen weiteren Adressen.
                Mails anderer Absender werden ignoriert. Das Postfach wird alle 5 Minuten abgerufen.
            </p>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4 text-sm">
                <button
                    type="button"
                    wire:click="fetchNow"
                    wire:loading.attr="disabled"
                    wire:target="fetchNow"
                    class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="fetchNow">Jetzt abrufen</span>
                    <span wire:loading wire:target="fetchNow">Wird abgerufen …</span>
                </button>

                <span class="text-slate-500">
                    {{ $settings->mail_posts_checked_at ? 'Zuletzt abgerufen '.$settings->mail_posts_checked_at->diffForHumans() : 'Noch nie abgerufen' }}
                </span>
            </div>

            @if($settings->mail_posts_last_error)
                <p class="text-sm text-red-600">Letzter Fehler: {{ $settings->mail_posts_last_error }}</p>
            @endif

            @if($recentPosts->isNotEmpty())
                <div class="border-t border-slate-100 pt-4">
                    <h4 class="mb-2 text-sm font-semibold text-slate-700">Zuletzt eingegangen</h4>
                    <ul class="space-y-1 text-sm">
                        @foreach($recentPosts as $recentPost)
                            <li wire:key="recent-{{ $recentPost->postID }}">
                                <a href="{{ route('homepage-admin.posts.edit', $recentPost) }}" wire:navigate class="font-medium text-slate-800 hover:underline">{{ $recentPost->title }}</a>
                                <span class="text-xs text-slate-500">· {{ $recentPost->submitted_by_email }} · {{ $recentPost->created_at->format('d.m.Y H:i') }} · {{ $recentPost->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        @if($isAdmin)
            <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold">Postfach <span class="text-sm font-normal text-slate-400">(nur Admins)</span></h3>

                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" wire:model="enabled" class="rounded border-slate-300">
                    Beiträge per E-Mail aktiv
                </label>

                <div>
                    <label class="mb-1 block text-sm font-medium">Adresse für Einreichungen</label>
                    <input type="email" wire:model="address" placeholder="homepage@mein-verein.at" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-500">Am besten ein eigenes Postfach nur für Beiträge – jede Mail darin wird verarbeitet.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-[1fr_100px_140px]">
                    <div>
                        <label class="mb-1 block text-sm font-medium">IMAP-Server</label>
                        <input type="text" wire:model="host" placeholder="imap.mein-anbieter.at" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('host') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Port</label>
                        <input type="number" wire:model="port" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Verschlüsselung</label>
                        <select wire:model="encryption" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="starttls">STARTTLS</option>
                            <option value="notls">keine</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Benutzername</label>
                        <input type="text" wire:model="username" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('username') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Passwort</label>
                        <input type="password" wire:model="password" autocomplete="new-password" placeholder="{{ $hasPassword ? '•••••••• (unverändert)' : '' }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="validateCert" class="rounded border-slate-300">
                    Zertifikat prüfen
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Ordner</label>
                        <input type="text" wire:model="folder" placeholder="INBOX" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Verarbeitete Mails verschieben nach</label>
                        <input type="text" wire:model="processedFolder" placeholder="leer = nur als gelesen markieren" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Weitere erlaubte Absender</label>
                    <textarea wire:model="allowedSenders" rows="3" placeholder="eine Adresse je Zeile, z. B. trainer@mein-verein.at" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"></textarea>
                    <p class="mt-1 text-xs text-slate-500">Zusätzlich zu allen VEMA-Benutzern mit der Rolle Homepage oder Admin.</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
                        Speichern
                    </button>

                    <button type="button" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60">
                        <span wire:loading.remove wire:target="testConnection">Gespeicherte Verbindung testen</span>
                        <span wire:loading wire:target="testConnection">Teste …</span>
                    </button>
                </div>
            </section>
        @endif

    </div>

</div>
