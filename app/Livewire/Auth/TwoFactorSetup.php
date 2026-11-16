<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TwoFactorAuthentication;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Zwei-Faktor-Anmeldung einrichten, Methode wechseln und
 * Wiederherstellungscodes erneuern.
 */
class TwoFactorSetup extends Component
{
    private const SECRET_SESSION_KEY = 'two_factor_setup_secret';

    /**
     * overview | confirm | codes
     */
    public string $step = 'overview';

    public string $method = TwoFactorAuthentication::METHOD_APP;

    public string $code = '';

    /**
     * Nur direkt nach dem Erzeugen sichtbar.
     *
     * @var list<string>
     */
    public array $recoveryCodes = [];

    public function mount(TwoFactorAuthentication $twoFactor): void
    {
        if (! $twoFactor->isEnabled($this->user())) {
            $this->step = 'choose';
        }
    }

    public function startSetup(): void
    {
        $this->step = 'choose';
        $this->resetErrorBag();
    }

    public function chooseMethod(TwoFactorAuthentication $twoFactor): void
    {
        $this->validate(['method' => ['required', Rule::in(array_keys(TwoFactorAuthentication::methodLabels()))]]);

        if ($this->method === TwoFactorAuthentication::METHOD_APP) {
            session()->put(self::SECRET_SESSION_KEY, $twoFactor->generateSecret());
        } else {
            $twoFactor->sendEmailCode($this->user());
        }

        $this->code = '';
        $this->step = 'confirm';
    }

    public function resendEmailCode(TwoFactorAuthentication $twoFactor): void
    {
        $twoFactor->sendEmailCode($this->user());

        session()->flash('success', 'Ein neuer Code wurde an '.$this->user()->email.' geschickt.');
    }

    public function confirm(TwoFactorAuthentication $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string']], ['code.required' => 'Bitte gib den Code ein.']);

        $user = $this->user();
        $secret = session(self::SECRET_SESSION_KEY);

        $valid = $this->method === TwoFactorAuthentication::METHOD_APP
            ? is_string($secret) && $twoFactor->verifyTotp($secret, $this->code)
            : $twoFactor->verifyEmailCode($user, $this->code);

        if (! $valid) {
            $this->addError('code', 'Der Code ist falsch oder abgelaufen.');

            return;
        }

        $wasEnabled = $twoFactor->isEnabled($user);
        $this->recoveryCodes = $twoFactor->enable($user, $this->method, is_string($secret) ? $secret : null);
        session()->forget(self::SECRET_SESSION_KEY);

        ActivityLogger::log(
            'user.two_factor_enabled',
            'Benutzer "'.$user->username.'" hat die Zwei-Faktor-Anmeldung '.($wasEnabled ? 'geändert' : 'eingerichtet')
                .' ('.TwoFactorAuthentication::methodLabels()[$this->method].').',
            'User',
            $user->id
        );

        $this->code = '';
        $this->step = 'codes';
    }

    public function regenerateRecoveryCodes(TwoFactorAuthentication $twoFactor): void
    {
        $user = $this->user();

        abort_unless($twoFactor->isEnabled($user), 403);

        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes($user);
        $this->step = 'codes';

        ActivityLogger::log(
            'user.two_factor_recovery_codes',
            'Benutzer "'.$user->username.'" hat neue Wiederherstellungscodes erzeugt.',
            'User',
            $user->id
        );
    }

    public function disable(TwoFactorAuthentication $twoFactor): void
    {
        abort_if($twoFactor->isRequired(), 403, 'Die Zwei-Faktor-Anmeldung ist in diesem Verein verpflichtend.');

        $user = $this->user();
        $twoFactor->disable($user);

        ActivityLogger::log(
            'user.two_factor_disabled',
            'Benutzer "'.$user->username.'" hat die Zwei-Faktor-Anmeldung ausgeschaltet.',
            'User',
            $user->id
        );

        $this->step = 'choose';
    }

    public function finish(): void
    {
        $this->recoveryCodes = [];
        $this->redirectRoute('dashboard', navigate: true);
    }

    private function user(): User
    {
        return auth()->guard('web')->user();
    }

    public function render(TwoFactorAuthentication $twoFactor): View
    {
        $user = $this->user();
        $secret = session(self::SECRET_SESSION_KEY);
        $showsApp = $this->step === 'confirm' && $this->method === TwoFactorAuthentication::METHOD_APP && is_string($secret);

        return view('livewire.auth.two-factor-setup', [
            'user' => $user,
            'isEnabled' => $twoFactor->isEnabled($user),
            'isRequired' => $twoFactor->isRequired(),
            'methodLabels' => TwoFactorAuthentication::methodLabels(),
            'remainingRecoveryCodes' => $twoFactor->remainingRecoveryCodes($user),
            'secret' => $showsApp ? $secret : null,
            'qrCodeSvg' => $showsApp ? $twoFactor->qrCodeSvg($twoFactor->otpauthUrl($user, $secret)) : null,
        ])->layout('layouts.app', [
            'title' => 'Zwei-Faktor-Anmeldung | VEMA',
            'heading' => 'Zwei-Faktor-Anmeldung',
        ]);
    }
}
