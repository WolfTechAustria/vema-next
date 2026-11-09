<?php

namespace App\Livewire\Application;

use App\Models\Setting;
use App\Services\MemberApplications;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Bestätigung der E-Mail-Adresse einer Online-Anmeldung. Bestätigt wird
 * erst per Klick, damit Link-Vorschauen von Mailprogrammen nichts auslösen.
 */
class Verify extends Component
{
    #[Locked]
    public string $token = '';

    public bool $confirmed = false;

    public bool $invalid = false;

    public function mount(string $token, MemberApplications $applications): void
    {
        $this->token = $token;
        $this->invalid = $applications->findUnverified($token) === null;
    }

    public function confirm(MemberApplications $applications): void
    {
        if ($applications->verify($this->token) === null) {
            $this->invalid = true;

            return;
        }

        $this->confirmed = true;
    }

    public function render()
    {
        return view('livewire.application.verify', [
            'clubName' => Setting::current()->name,
        ])->layout('layouts.member-auth', [
            'title' => 'Anmeldung bestätigen | '.Setting::current()->name,
        ]);
    }
}
