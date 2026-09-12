<?php

namespace App\Livewire\MemberPortal;

use App\Models\Member;
use App\Models\MemberPhone;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';
    public string $surname = '';
    public string $street = '';
    public string $zip = '';

    public array $phones = [];

    public function mount(): void
    {
        $member = $this->activeMember();

        $member->load('phones');

        $this->name = $member->name ?? '';
        $this->surname = $member->surname ?? '';
        $this->street = $member->street ?? '';
        $this->zip = $member->zip ?? '';

        $this->phones = $member->phones
            ->map(fn ($phone) => [
                'ID' => $phone->ID,
                'phoneCategory' => (int) $phone->phoneCategory,
                'phoneNumber' => $phone->phoneNumber ?? '',
            ])
            ->values()
            ->all();
    }

    public function addPhone(): void
    {
        $this->phones[] = [
            'ID' => null,
            'phoneCategory' => 1,
            'phoneNumber' => '',
        ];
    }

    public function removePhone(int $index): void
    {
        if (!isset($this->phones[$index])) {
            return;
        }

        $member = $this->activeMember();

        $phoneID = $this->phones[$index]['ID'] ?? null;

        if ($phoneID) {
            MemberPhone::query()
                ->where('ID', $phoneID)
                ->where('memberID', $member->memberID)
                ->delete();
        }

        unset($this->phones[$index]);

        $this->phones = array_values($this->phones);
    }

    public function saveProfile(): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'surname' => [
                'required',
                'string',
                'max:255',
            ],

            'street' => [
                'required',
                'string',
                'max:255',
            ],

            'zip' => [
                'required',
                'string',
                'max:20',
            ],

            'phones' => [
                'array',
            ],

            'phones.*.ID' => [
                'nullable',
                'integer',
            ],

            'phones.*.phoneCategory' => [
                'required',
                'integer',
                'between:1,6',
            ],

            'phones.*.phoneNumber' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $member = $this->activeMember();

        /*
         * Stammdaten speichern
         */
        $member->update([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'street' => $validated['street'],
            'zip' => $validated['zip'],
        ]);

        /*
         * Telefonnummern speichern
         */
        foreach ($validated['phones'] as $index => $phoneData) {
            $phoneNumber = trim($phoneData['phoneNumber'] ?? '');

            /*
             * Leere neue Telefonnummern ignorieren.
             */
            if ($phoneNumber === '') {
                continue;
            }

            $phoneID = $phoneData['ID'] ?? null;

            /*
             * Bestehende Telefonnummer aktualisieren.
             */
            if ($phoneID) {
                MemberPhone::query()
                    ->where('ID', $phoneID)
                    ->where('memberID', $member->memberID)
                    ->update([
                        'phoneCategory' => $phoneData['phoneCategory'],
                        'phoneNumber' => $phoneNumber,
                    ]);

                continue;
            }

            /*
             * Neue Telefonnummer erstellen.
             */
            $phone = MemberPhone::create([
                'memberID' => $member->memberID,
                'phoneCategory' => $phoneData['phoneCategory'],
                'phoneNumber' => $phoneNumber,
            ]);

            /*
             * Neue Datenbank-ID zurück ins Livewire-Array schreiben.
             */
            $this->phones[$index]['ID'] = $phone->ID;
            $this->phones[$index]['phoneNumber'] = $phoneNumber;
        }

        session()->flash(
            'success',
            'Deine Stammdaten wurden gespeichert.'
        );
    }

    private function activeMember(): Member
    {
        $account = Auth::guard('member')->user();

        abort_unless(
            $account,
            403
        );

        $memberID = session('active_member_id');

        abort_unless(
            $memberID,
            403,
            'Kein Mitgliederprofil ausgewählt.'
        );

        /*
         * Wichtig:
         *
         * Das ausgewählte Mitglied muss aktiv sein UND die
         * Login-E-Mail des Accounts muss beim Mitglied in
         * tb_email hinterlegt sein.
         *
         * Dadurch kann nicht einfach eine fremde memberID
         * in die Session geschrieben werden.
         */
        return Member::query()
            ->where('memberID', $memberID)
            ->where('active', true)
            ->whereHas('emails', function ($query) use ($account) {
                $query->where('email', $account->email);
            })
            ->firstOrFail();
    }

    public function render()
    {
        $account = Auth::guard('member')->user();

        abort_unless(
            $account,
            403
        );

        $member = $this->activeMember()
            ->load([
                'city',
                'emails',
                'phones',
            ]);

        return view(
            'livewire.member-portal.profile',
            [
                'account' => $account,
                'member' => $member,
            ]
        )->layout('layouts.app', [
            'title' => 'Mein Profil | VEMA',
            'heading' => 'Mein Profil',
        ]);
    }
}
