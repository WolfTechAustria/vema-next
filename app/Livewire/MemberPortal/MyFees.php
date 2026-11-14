<?php

namespace App\Livewire\MemberPortal;

use App\Enums\Feature;
use App\Models\Member;
use App\Models\MembershipFeeEntry;
use App\Models\SepaMandate;
use App\Rules\ValidIban;
use App\Services\ActiveMemberResolver;
use App\Services\PlanEntitlements;
use App\Services\Sepa\SepaValidator;
use App\Services\SepaMandates;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MyFees extends Component
{
    /** Formular für ein (neues) Lastschriftmandat geöffnet */
    public bool $mandateFormOpen = false;

    public string $accountHolder = '';

    public string $iban = '';

    public string $bic = '';

    public bool $consent = false;

    public function openMandateForm(SepaMandates $mandates): void
    {
        $this->ensureMandatesAvailable($mandates);

        $this->mandateFormOpen = true;
        $this->accountHolder = $this->member()->full_name;
        $this->iban = '';
        $this->bic = '';
        $this->consent = false;
        $this->resetErrorBag();
    }

    public function closeMandateForm(): void
    {
        $this->mandateFormOpen = false;
    }

    public function grantMandate(SepaMandates $mandates): void
    {
        $this->ensureMandatesAvailable($mandates);

        $this->validate([
            'accountHolder' => ['required', 'string', 'max:70'],
            'iban' => ['required', 'string', new ValidIban],
            'bic' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! app(SepaValidator::class)->isValidBic($value)) {
                    $fail('Bitte einen gültigen BIC eingeben oder das Feld leer lassen.');
                }
            }],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Bitte bestätige das Lastschriftmandat.',
        ], [
            'accountHolder' => 'Kontoinhaber',
        ]);

        $mandates->grant(
            $this->member(),
            $this->accountHolder,
            $this->iban,
            $this->bic ?: null,
            SepaMandate::SOURCE_PORTAL,
            notifyClub: true,
        );

        $this->mandateFormOpen = false;

        session()->flash('success', 'Danke! Das Lastschriftmandat wurde erteilt – künftige Beiträge werden von deinem Konto eingezogen.');
    }

    public function revokeMandate(SepaMandates $mandates): void
    {
        $this->ensureMandatesAvailable($mandates);

        $mandate = $this->member()->activeSepaMandate()->first() ?? abort(404);

        $mandates->revoke($mandate, byMember: true);

        session()->flash('success', 'Das Lastschriftmandat wurde widerrufen. Bitte überweise künftige Beiträge selbst.');
    }

    private function member(): Member
    {
        return app(ActiveMemberResolver::class)->resolve();
    }

    private function mandatesAvailable(SepaMandates $mandates): bool
    {
        return app(PlanEntitlements::class)->allows(Feature::SepaDirectDebit)
            && app(SepaValidator::class)->isValidCreditorId($mandates->creditorId());
    }

    private function ensureMandatesAvailable(SepaMandates $mandates): void
    {
        abort_unless($this->mandatesAvailable($mandates), 403);
    }

    public function render(SepaMandates $mandates): View
    {
        $member = $this->member();

        $entries = MembershipFeeEntry::query()
            ->where('memberID', $member->memberID)
            ->with([
                'year',
                'prescriptions' => fn ($query) => $query
                    ->whereNotNull('sent_at')
                    ->orderBy('sent_at'),
            ])
            ->get()
            ->sortByDesc(fn (MembershipFeeEntry $entry) => $entry->year?->year)
            ->values();

        $mandatesAvailable = $this->mandatesAvailable($mandates);

        return view('livewire.member-portal.my-fees', [
            'entries' => $entries,
            'openAmount' => $entries->where('status', 'open')->sum('amount'),
            'mandatesAvailable' => $mandatesAvailable,
            'mandate' => $mandatesAvailable ? $member->activeSepaMandate()->first() : null,
            'consentText' => $mandatesAvailable ? $mandates->consentText() : '',
        ])->layout('layouts.app', [
            'title' => 'Meine Beiträge | VEMA',
            'heading' => 'Meine Beiträge',
        ]);
    }
}
