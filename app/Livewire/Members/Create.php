<?php

namespace App\Livewire\Members;

use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\MemberPhone;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';
    public string $surname = '';
    public string $gender = '';
    public ?string $dateOfBirth = null;
    public ?string $dateOfJoin = null;
    public string $street = '';
    public string $zip = '';
    public bool $active = true;
    public bool $competitionMember = false;
    public bool $supportingMember = false;
    public string $board_function = '';

    public array $emails = [];
    public array $phones = [];

    protected function rules(): array
    {
        return [
            'gender' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'dateOfBirth' => ['nullable', 'date'],
            'dateOfJoin' => ['nullable', 'date'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'regex:/^\d{4}$/', 'max:20'],
            'active' => ['boolean'],
            'competitionMember' => ['boolean'],
            'supportingMember' => ['boolean'],
            'board_function' => ['nullable', 'string', 'max:100'],

            'emails' => ['array'],
            'emails.*.email' => ['required', 'email', 'max:255'],

            'phones' => ['array'],
            'phones.*.phoneCategory' => ['required', 'integer'],
            'phones.*.phoneNumber' => ['required', 'string', 'max:100'],
        ];
    }

    public function addEmail(): void
    {
        $this->emails[] = [
            'email' => '',
        ];
    }

    public function removeEmail(int $index): void
    {
        unset($this->emails[$index]);
        $this->emails = array_values($this->emails);
    }

    public function addPhone(): void
    {
        $this->phones[] = [
            'phoneCategory' => 1,
            'phoneNumber' => '',
        ];
    }

    public function removePhone(int $index): void
    {
        unset($this->phones[$index]);
        $this->phones = array_values($this->phones);
    }

    public function save()
    {
        $validated = $this->validate();

        $member = DB::transaction(function () use ($validated) {
            $member = Member::create([
                'gender' => $validated['gender'],
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'dateOfBirth' => $validated['dateOfBirth'] ?: null,
                'dateOfJoin' => $validated['dateOfJoin'] ?: null,
                'street' => $validated['street'],
                'zip' => $validated['zip'],
                'active' => $validated['active'],
                'competitionMember' => $validated['competitionMember'],
                'supportingMember' => $validated['supportingMember'],
                'board_function' => $validated['board_function'] ?: null,
            ]);

            foreach ($this->emails as $email) {
                MemberEmail::create([
                    'memberID' => $member->memberID,
                    'email' => $email['email'],
                ]);
            }

            foreach ($this->phones as $phone) {
                MemberPhone::create([
                    'memberID' => $member->memberID,
                    'phoneCategory' => $phone['phoneCategory'],
                    'phoneNumber' => $phone['phoneNumber'],
                ]);
            }

            return $member;
        });

        session()->flash('success', 'Mitglied wurde angelegt.');

        return $this->redirectRoute(
            'members.show',
            ['member' => $member->memberID],
            navigate: true
        );
    }

    protected function messages(): array
    {
        return [
            'zip.regex' => 'Bitte nur die 4-stellige Postleitzahl eingeben, ohne Ortsname (z. B. 1010).',
        ];
    }

    public function updatedZip(string $value): void
    {
        // Falls jemand "1010 Wien" statt "1010" eingibt: automatisch nur die
        // führenden Ziffern übernehmen. Der Ortsname wird ohnehin separat aus
        // tb_city anhand der PLZ ermittelt und muss hier nicht eingegeben werden.
        if (preg_match('/^(\d{4,})\D.*$/', trim($value), $matches)) {
            $this->zip = $matches[1];
        }
    }

    public function render()
    {
        return view('livewire.members.create')
            ->layout('layouts.app', [
                'title' => 'Mitglied hinzufügen | VEMA',
                'heading' => 'Mitglied hinzufügen',
            ]);
    }
}
