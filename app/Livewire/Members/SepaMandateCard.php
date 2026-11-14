<?php

namespace App\Livewire\Members;

use App\Enums\Feature;
use App\Models\Member;
use App\Models\SepaMandate;
use App\Rules\ValidIban;
use App\Services\PlanEntitlements;
use App\Services\Sepa\SepaValidator;
use App\Services\SepaMandates;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * SEPA-Lastschriftmandat beim Mitglied: Admins und Kassiere sehen die
 * Bankdaten und erfassen/widerrufen Mandate, alle anderen nur „ja/nein“.
 */
class SepaMandateCard extends Component
{
    #[Locked]
    public Member $member;

    public bool $editing = false;

    public string $accountHolder = '';

    public string $iban = '';

    public string $bic = '';

    public string $signedOn = '';

    public function startEditing(): void
    {
        $this->ensureTreasurer();

        $this->editing = true;
        $this->accountHolder = $this->member->full_name;
        $this->iban = '';
        $this->bic = '';
        $this->signedOn = today()->toDateString();
        $this->resetErrorBag();
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
    }

    public function save(SepaMandates $mandates): void
    {
        $this->ensureTreasurer();

        $this->validate([
            'accountHolder' => ['required', 'string', 'max:70'],
            'iban' => ['required', 'string', new ValidIban],
            'bic' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! app(SepaValidator::class)->isValidBic($value)) {
                    $fail('Bitte einen gültigen BIC eingeben oder das Feld leer lassen.');
                }
            }],
            'signedOn' => ['required', 'date', 'before_or_equal:today'],
        ], [], [
            'accountHolder' => 'Kontoinhaber',
            'signedOn' => 'Datum der Unterschrift',
        ]);

        $mandates->grant(
            $this->member,
            $this->accountHolder,
            $this->iban,
            $this->bic ?: null,
            SepaMandate::SOURCE_MANUAL,
            Carbon::parse($this->signedOn),
        );

        $this->editing = false;
        $this->member->unsetRelation('activeSepaMandate');

        session()->flash('success', 'Das SEPA-Mandat wurde gespeichert.');
    }

    public function revoke(SepaMandates $mandates): void
    {
        $this->ensureTreasurer();

        $mandate = $this->member->activeSepaMandate()->first();

        if ($mandate) {
            $mandates->revoke($mandate);
            $this->member->unsetRelation('activeSepaMandate');
            session()->flash('success', 'Das SEPA-Mandat wurde widerrufen.');
        }
    }

    private function isTreasurer(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'kassier']);
    }

    private function ensureTreasurer(): void
    {
        abort_unless($this->isTreasurer() && app(PlanEntitlements::class)->allows(Feature::SepaDirectDebit), 403);
    }

    public function render(): View
    {
        return view('livewire.members.sepa-mandate-card', [
            'available' => app(PlanEntitlements::class)->allows(Feature::SepaDirectDebit),
            'isTreasurer' => $this->isTreasurer(),
            'mandate' => $this->member->activeSepaMandate()->first(),
        ]);
    }
}
