<div class="mx-auto w-full max-w-2xl">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        {{-- Kopf --}}
        <div class="border-b border-slate-200 px-6 py-8 text-center sm:px-8">
            @php
                $clubLogoUrl = app(\App\Services\ClubBranding::class)->logoUrl();
            @endphp

            @if($clubLogoUrl)
                <img src="{{ $clubLogoUrl }}" alt="{{ $clubName }}" class="mx-auto mb-5 max-h-32 w-auto">
            @endif

            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Mitglied werden</h1>

            <p class="mt-1 text-sm text-slate-500">Beitrittserklärung · {{ $clubName }}</p>
        </div>

        @if($result === 'verify')

            <div class="px-6 py-10 text-center sm:px-8">
                <p class="text-lg font-semibold text-slate-900">Fast geschafft!</p>
                <p class="mt-2 text-slate-600">
                    Wir haben dir eine E-Mail an <strong>{{ $email }}</strong> geschickt.
                    Bitte bestätige deine Anmeldung mit dem Link darin. Erst danach wird sie an den Verein weitergeleitet.
                </p>
                <p class="mt-4 text-sm text-slate-500">Keine E-Mail erhalten? Bitte auch im Spam-Ordner nachsehen.</p>
            </div>

        @elseif($result === 'done')

            <div class="px-6 py-10 text-center sm:px-8">
                <p class="text-lg font-semibold text-slate-900">Vielen Dank!</p>
                <p class="mt-2 text-slate-600">
                    Deine Anmeldung ist eingegangen. Der Verein prüft sie und meldet sich bei dir.
                </p>
            </div>

        @else

            <form wire:submit="submit" novalidate class="space-y-8 px-6 py-7 sm:px-8">

                @if($settings->intro())
                    <div class="whitespace-pre-line rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $settings->intro() }}</div>
                @endif

                {{-- Persönliche Daten --}}
                <section class="space-y-4">
                    <h2 class="font-semibold text-slate-900">Persönliche Daten</h2>

                    @if($settings->shows('gender'))
                        <div>
                            <span class="mb-1.5 block text-sm font-medium text-slate-700">Anrede @unless($settings->requires('gender'))<span class="font-normal text-slate-400">(optional)</span>@endunless</span>
                            <div class="flex gap-6">
                                <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model="gender" value="w" class="border-slate-300"> Frau</label>
                                <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model="gender" value="m" class="border-slate-300"> Herr</label>
                            </div>
                            @error('gender') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="app-name" class="mb-1.5 block text-sm font-medium text-slate-700">Vorname</label>
                            <input id="app-name" type="text" wire:model="name" autocomplete="given-name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="app-surname" class="mb-1.5 block text-sm font-medium text-slate-700">Nachname</label>
                            <input id="app-surname" type="text" wire:model="surname" autocomplete="family-name" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('surname') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if($settings->shows('dateOfBirth'))
                        <div class="sm:w-1/2">
                            <label for="app-dob" class="mb-1.5 block text-sm font-medium text-slate-700">Geburtsdatum @unless($settings->requires('dateOfBirth'))<span class="font-normal text-slate-400">(optional)</span>@endunless</label>
                            <input id="app-dob" type="date" wire:model.live.blur="dateOfBirth" autocomplete="bday" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('dateOfBirth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if($settings->shows('address'))
                        @php $addressOptional = ! $settings->requires('address'); @endphp
                        <div>
                            <label for="app-street" class="mb-1.5 block text-sm font-medium text-slate-700">Straße und Hausnummer @if($addressOptional)<span class="font-normal text-slate-400">(optional)</span>@endif</label>
                            <input id="app-street" type="text" wire:model="street" autocomplete="street-address" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('street') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="app-zip" class="mb-1.5 block text-sm font-medium text-slate-700">PLZ</label>
                                <input id="app-zip" type="text" inputmode="numeric" wire:model.live.blur="zip" autocomplete="postal-code" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('zip') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="app-city" class="mb-1.5 block text-sm font-medium text-slate-700">Ort</label>
                                <input id="app-city" type="text" wire:model="city" autocomplete="address-level2" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('city') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="app-email" class="mb-1.5 block text-sm font-medium text-slate-700">E-Mail</label>
                            <input id="app-email" type="email" inputmode="email" wire:model="email" autocomplete="email" autocapitalize="none" spellcheck="false" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        @if($settings->shows('phone'))
                            <div>
                                <label for="app-phone" class="mb-1.5 block text-sm font-medium text-slate-700">Telefon @unless($settings->requires('phone'))<span class="font-normal text-slate-400">(optional)</span>@endunless</label>
                                <input id="app-phone" type="tel" wire:model="phone" autocomplete="tel" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    @foreach($customFieldDefinitions as $field)
                        <div wire:key="custom-field-{{ $field->fieldID }}">
                            @if($field->type === \App\Enums\MemberFieldType::Boolean)
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="customFields.{{ $field->fieldID }}" class="rounded border-slate-300">
                                    {{ $field->name }}
                                </label>
                            @else
                                <label class="mb-1.5 block text-sm font-medium text-slate-700">{{ $field->name }} <span class="font-normal text-slate-400">(optional)</span></label>

                                @if($field->type === \App\Enums\MemberFieldType::MultiSelect)
                                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                                        @foreach($field->options ?? [] as $option)
                                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" wire:model="customFields.{{ $field->fieldID }}" value="{{ $option }}" class="rounded border-slate-300">
                                                {{ $option }}
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($field->type === \App\Enums\MemberFieldType::Select)
                                    <select wire:model="customFields.{{ $field->fieldID }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                        <option value="">–</option>
                                        @foreach($field->options ?? [] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        type="{{ $field->type === \App\Enums\MemberFieldType::Date ? 'date' : 'text' }}"
                                        @if($field->type === \App\Enums\MemberFieldType::Number) inputmode="decimal" @endif
                                        wire:model="customFields.{{ $field->fieldID }}"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                                    >
                                @endif
                            @endif
                            @error('customFields.'.$field->fieldID) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </section>

                {{-- Mitgliedsart --}}
                @if($membershipTypes !== [])
                    <section class="space-y-3">
                        <h2 class="font-semibold text-slate-900">Mitgliedsart</h2>

                        @foreach($membershipTypes as $index => $type)
                            <label wire:key="membership-type-{{ $index }}" class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 hover:bg-slate-50 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-50">
                                <input type="radio" wire:model="membershipType" value="{{ $index }}" class="mt-1 border-slate-300">
                                <span>
                                    <span class="block font-medium text-slate-900">{{ $type['label'] }}</span>
                                    @if($type['info'] !== '')
                                        <span class="mt-1 block whitespace-pre-line text-sm text-slate-600">{{ $type['info'] }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach

                        @error('membershipType') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </section>
                @endif

                {{-- Beitritt, Ort, Unterschrift --}}
                <section class="space-y-4">
                    <h2 class="font-semibold text-slate-900">Beitritt</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <span class="mb-1.5 block text-sm font-medium text-slate-700">Beitrittsdatum</span>
                            <div class="rounded-lg bg-slate-50 px-3 py-2 text-slate-700">{{ $today->format('d.m.Y') }}</div>
                        </div>

                        @if($settings->shows('place'))
                            <div>
                                <label for="app-place" class="mb-1.5 block text-sm font-medium text-slate-700">Ort @unless($settings->requires('place'))<span class="font-normal text-slate-400">(optional)</span>@endunless</label>
                                <input id="app-place" type="text" wire:model="place" placeholder="{{ $city }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                                @error('place') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    @if($this->isMinor())
                        <div>
                            <label for="app-guardian" class="mb-1.5 block text-sm font-medium text-slate-700">Name der/des Erziehungsberechtigten</label>
                            <input id="app-guardian" type="text" wire:model="guardianName" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <p class="mt-1 text-xs text-slate-500">Bei Minderjährigen unterschreibt eine erziehungsberechtigte Person.</p>
                            @error('guardianName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if($settings->requiresSignature())
                        <div x-data="signaturePad($wire.entangle('signature'))">
                            <div class="mb-1.5 flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-700">Unterschrift</span>
                                <button type="button" x-on:click="clear()" class="text-sm text-slate-500 hover:text-slate-800">Löschen</button>
                            </div>

                            <div wire:ignore class="rounded-lg border-2 border-dashed border-slate-300 bg-white">
                                <canvas x-ref="canvas" class="block h-40 w-full touch-none"></canvas>
                            </div>

                            <p class="mt-1 text-xs text-slate-500">Mit dem Finger oder der Maus im Feld unterschreiben.</p>
                            @error('signature') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </section>

                {{-- Datenschutz --}}
                @if($settings->privacyText() || $consentTexts !== [])
                    <section class="space-y-3">
                        <h2 class="font-semibold text-slate-900">Datenschutz</h2>

                        @if($settings->privacyText())
                            <div class="max-h-60 overflow-y-auto whitespace-pre-line rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">{{ $settings->privacyText() }}</div>
                        @endif

                        @foreach($consentTexts as $index => $consent)
                            <div wire:key="consent-{{ $index }}">
                                <label class="flex gap-3 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="consents.{{ $index }}" class="mt-0.5 rounded border-slate-300">
                                    <span>{{ $consent['text'] }} @unless($consent['required'])<span class="text-slate-400">(freiwillig)</span>@endunless</span>
                                </label>
                                @error('consents.'.$index) <p class="ml-7 mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </section>
                @endif

                {{-- Honeypot --}}
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
                </div>

                @if($captchaSiteKey)
                    <div
                        wire:ignore
                        x-data="turnstileWidget('{{ $captchaSiteKey }}', (token) => $wire.set('captchaToken', token, false))"
                        x-on:captcha-reset.window="reset()"
                    >
                        <div x-ref="box"></div>
                    </div>
                    @error('captcha') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @endif

                @error('form') <p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p> @enderror

                @if($errors->isNotEmpty())
                    <p class="text-sm text-red-600">Bitte die markierten Felder prüfen.</p>
                @endif

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="submit">Anmeldung absenden</span>
                    <span wire:loading wire:target="submit">Wird gesendet …</span>
                </button>

            </form>

        @endif

    </div>

</div>
