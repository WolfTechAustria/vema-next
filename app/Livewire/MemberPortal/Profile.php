<?php

namespace App\Livewire\MemberPortal;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use App\Models\MemberPhone;

class Profile extends Component
{
    public string $name = '';
    public string $surname = '';
    public string $street = '';
    public string $zip = '';
    public array $phones = [];

    public function mount(): void
    {
        $account = Auth::guard('member')->user();

        abort_unless($account, 403);

        $member = $account->member()
            ->with('phones')
            ->firstOrFail();

        abort_unless($member, 404);

        $this->phones = $member->phones
            ->map(fn ($phone) => [
                'ID' => $phone->ID,
                'phoneCategory' => (int) $phone->phoneCategory,
                'phoneNumber' => $phone->phoneNumber,
            ])
            ->values()
            ->all();



        $this->name = $member->name ?? '';
        $this->surname = $member->surname ?? '';
        $this->street = $member->street ?? '';
        $this->zip = $member->zip ?? '';
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

        $account = Auth::guard('member')->user();

        abort_unless($account, 403);

        $member = $account->member;

        abort_unless($member, 404);

        $member->update([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'street' => $validated['street'],
            'zip' => $validated['zip'],
        ]);

        foreach ($validated['phones'] as $phoneData) {

            /*
             * Leere neue Telefonnummern nicht speichern.
             */
            if (blank($phoneData['phoneNumber'] ?? null)) {
                continue;
            }

            if (!empty($phoneData['ID'])) {

                MemberPhone::query()
                    ->where('ID', $phoneData['ID'])
                    ->where('memberID', $member->memberID)
                    ->update([
                        'phoneCategory' => $phoneData['phoneCategory'],
                        'phoneNumber' => $phoneData['phoneNumber'],
                    ]);

            } else {

                $phone = MemberPhone::create([
                    'memberID' => $member->memberID,
                    'phoneCategory' => $phoneData['phoneCategory'],
                    'phoneNumber' => $phoneData['phoneNumber'],
                ]);

                /*
                 * ID ins Livewire-Array übernehmen.
                 */
                foreach ($this->phones as $index => $existingPhone) {

                    if (
                        empty($existingPhone['ID'])
                        && $existingPhone['phoneNumber'] === $phoneData['phoneNumber']
                        && (int) $existingPhone['phoneCategory'] === (int) $phoneData['phoneCategory']
                    ) {
                        $this->phones[$index]['ID'] = $phone->ID;
                        break;
                    }
                }
            }
        }

        session()->flash(
            'success',
            'Deine Stammdaten wurden gespeichert.'
        );
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

        $phoneID = $this->phones[$index]['ID'] ?? null;

        if ($phoneID) {
            MemberPhone::query()
                ->where('ID', $phoneID)
                ->where('memberID', Auth::guard('member')->user()->memberID)
                ->delete();
        }

        unset($this->phones[$index]);

        $this->phones = array_values(
            $this->phones
        );
    }

    public function render()
    {
        $account = Auth::guard('member')->user();

        abort_unless($account, 403);

        $member = $account->member()
            ->with([
                'city',
                'emails',
                'phones',
            ])
            ->firstOrFail();

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
