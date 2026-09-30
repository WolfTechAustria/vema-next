<?php

namespace App\Livewire\Central;

use App\Enums\TenantStatus;
use App\Mail\Central\ClubLinksMail;
use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * app.vemat.at/login: Jeder Verein meldet sich auf seiner eigenen Adresse an.
 * Hier findet man sie — per Vereinsadresse oder per Mail an die Kontaktadresse.
 */
class FindClub extends Component
{
    private const MAX_LINK_REQUESTS = 3;

    public string $address = '';

    /**
     * ?ziel=mitglieder: weiter zum Mitglieder-Login statt zum Vorstand-Login.
     */
    #[Url]
    public string $ziel = '';

    public string $email = '';

    public ?string $statusMessage = null;

    public function openClub(): void
    {
        $slug = $this->normalizeAddress($this->address);

        $this->validate(
            ['address' => ['required']],
            ['address.required' => 'Bitte gib die Adresse eures Vereins ein.']
        );

        $tenant = $slug !== '' ? Tenant::query()->where('slug', $slug)->first() : null;

        if (! $tenant || $tenant->status === TenantStatus::Pending) {
            $this->addError('address', 'Unter dieser Adresse ist kein Verein eingerichtet.');

            return;
        }

        $this->redirect($tenant->url().($this->ziel === 'mitglieder' ? '/member/login' : '/login'));
    }

    public function sendLinks(): void
    {
        $this->email = trim($this->email);
        $this->statusMessage = null;

        $this->validate(
            ['email' => ['required', 'email:rfc,filter', 'max:150']],
            [
                'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
                'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
            ]
        );

        $throttleKey = 'find-club:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LINK_REQUESTS)) {
            $this->addError('email', 'Es wurden bereits mehrere Anfragen gestellt. Bitte versuche es später erneut.');

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $tenants = Tenant::query()
            ->where('contact_email', Str::lower($this->email))
            ->where('status', '!=', TenantStatus::Pending)
            ->orderBy('name')
            ->get();

        if ($tenants->isNotEmpty()) {
            Mail::to($this->email)->send(new ClubLinksMail($tenants));
        }

        // Nicht verraten, ob die Adresse bekannt ist.
        $this->statusMessage = 'Wenn diese E-Mail-Adresse als Kontakt eines Vereins hinterlegt ist, haben wir dir die Links geschickt.';
    }

    /**
     * Akzeptiert "musikverein", "musikverein.vemat.at" oder die ganze URL.
     */
    private function normalizeAddress(string $address): string
    {
        $address = Str::lower(trim($address));
        $address = preg_replace('#^https?://#', '', $address);
        $address = Str::before($address, '/');

        return Str::before($address, '.'.Str::lower(config('tenancy.tenant_domain')));
    }

    public function render()
    {
        return view('livewire.central.find-club')
            ->layout('layouts.central', [
                'title' => 'Anmelden | VEMA',
            ]);
    }
}
