{{--
    Eigene Mitgliederfelder im Formular (Anlegen, Bearbeiten).
    Erwartet $customFieldDefinitions und die Livewire-Property $customFields.
--}}
@if($customFieldDefinitions->isNotEmpty())
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">
            <h3 class="font-semibold text-slate-900">
                Weitere Angaben
            </h3>
        </div>

        <div class="grid gap-5 p-6 sm:grid-cols-2">
            @foreach($customFieldDefinitions as $field)
                @include('livewire.members.partials.custom-field-input', ['field' => $field])
            @endforeach
        </div>

    </section>
@endif
