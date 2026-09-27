<?php

namespace App\Livewire\Central;

use App\Enums\Plan;
use App\Services\TenantRegistration;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Selbstregistrierung eines Vereins auf der Plattform (app.vemat.at).
 */
class Register extends Component
{
    /**
     * Registrierungen je IP und Stunde.
     */
    private const MAX_REGISTRATIONS_PER_HOUR = 5;

    /**
     * Paket aus dem Link der Website, z. B. ?plan=verein_plus.
     */
    #[Url(as: 'plan')]
    public string $requestedPlan = '';

    public string $club_name = '';

    public string $slug = '';

    public string $contact_name = '';

    public string $contact_email = '';

    public bool $accept_terms = false;

    /**
     * Honeypot: bleibt für Menschen unsichtbar und leer.
     */
    public string $website = '';

    /**
     * Solange die Adresse nicht selbst bearbeitet wurde, folgt sie dem Namen.
     */
    public bool $slugEdited = false;

    public ?string $registeredEmail = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'club_name' => ['required', 'string', 'min:3', 'max:150'],
            'slug' => TenantRegistration::slugRules(),
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'email:rfc,filter', 'max:150'],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'club_name.required' => 'Bitte gib den Namen des Vereins ein.',
            'club_name.min' => 'Der Vereinsname ist zu kurz.',
            'slug.required' => 'Bitte wähle eine Adresse für euren Verein.',
            'slug.min' => 'Die Adresse muss mindestens 3 Zeichen lang sein.',
            'slug.max' => 'Die Adresse darf höchstens 40 Zeichen lang sein.',
            'slug.regex' => 'Nur Kleinbuchstaben, Ziffern und Bindestriche (nicht am Anfang oder Ende).',
            'slug.not_regex' => 'Bitte keine doppelten Bindestriche verwenden.',
            'slug.not_in' => 'Diese Adresse ist reserviert. Bitte wähle eine andere.',
            'slug.unique' => 'Diese Adresse ist bereits vergeben. Bitte wähle eine andere.',
            'contact_name.required' => 'Bitte gib deinen Namen ein.',
            'contact_email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
            'contact_email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein (z. B. name@beispiel.at).',
            'accept_terms.accepted' => 'Bitte bestätige die AGB und die Datenschutzerklärung.',
        ];
    }

    public function updatedClubName(): void
    {
        if (! $this->slugEdited) {
            $this->slug = TenantRegistration::suggestSlug($this->club_name);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugEdited = true;
        $this->slug = Str::lower(trim($this->slug));

        if ($this->slug !== '') {
            $this->validateOnly('slug');
        }
    }

    public function register(TenantRegistration $registration): void
    {
        // Bots füllen das versteckte Feld aus — so tun, als hätte alles geklappt.
        if ($this->website !== '') {
            $this->registeredEmail = $this->contact_email;

            return;
        }

        $this->club_name = trim($this->club_name);
        $this->contact_name = trim($this->contact_name);
        $this->contact_email = trim($this->contact_email);
        $this->slug = Str::lower(trim($this->slug));

        $validated = $this->validate();

        $throttleKey = 'tenant-registration:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_REGISTRATIONS_PER_HOUR)) {
            $this->addError('club_name', 'Von diesem Anschluss wurden bereits mehrere Vereine registriert. Bitte versuche es später erneut.');

            return;
        }

        RateLimiter::hit($throttleKey, 3600);

        $registration->register($validated, Plan::fromWebsite($this->requestedPlan));

        $this->registeredEmail = $validated['contact_email'];
    }

    public function render()
    {
        return view('livewire.central.register', [
            'plan' => Plan::fromWebsite($this->requestedPlan),
        ])->layout('layouts.central', [
            'title' => 'Verein registrieren | VEMA',
        ]);
    }
}
