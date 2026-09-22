<?php

namespace App\Livewire\Members;

use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\MemberPhone;
use App\Models\Skill;
use App\Services\ActivityLogger;
use Livewire\Component;

class Edit extends Component
{
    public Member $member;

    public string $name = '';

    public string $surname = '';

    public ?string $dateOfBirth = null;

    public ?string $dateOfJoin = null;

    public string $street = '';

    public string $zip = '';

    public bool $competitionMember = false;

    public bool $supportingMember = false;

    public array $emails = [];

    public array $phones = [];

    public array $selectedSkills = [];

    public function mount(Member $member): void
    {
        $this->member = $member;

        $this->name = $member->name ?? '';
        $this->surname = $member->surname ?? '';
        $this->dateOfBirth = $member->dateOfBirth?->format('Y-m-d');
        $this->dateOfJoin = $member->dateOfJoin?->format('Y-m-d');
        $this->street = $member->street ?? '';
        $this->zip = $this->sanitizeZip($member->zip ?? '');
        $this->competitionMember = (bool) $member->competitionMember;
        $this->supportingMember = (bool) $member->supportingMember;

        $this->emails = $member->emails
            ->map(fn ($email) => [
                'id' => $email->getKey(),
                'email' => $email->email,
            ])
            ->values()
            ->toArray();

        $this->phones = $member->phones
            ->map(fn ($phone) => [
                'id' => $phone->getKey(),
                'phoneCategory' => (int) $phone->phoneCategory,
                'phoneNumber' => $phone->phoneNumber,
            ])
            ->values()
            ->toArray();

        $this->selectedSkills = $member->skills
            ->pluck('skillID')
            ->map(fn ($skillID) => (string) $skillID)
            ->values()
            ->all();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'dateOfBirth' => ['nullable', 'date'],
            'dateOfJoin' => ['nullable', 'date'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'competitionMember' => ['boolean'],
            'supportingMember' => ['boolean'],

            'emails' => ['array'],
            'emails.*.email' => ['required', 'email', 'max:255'],

            'phones' => ['array'],
            'phones.*.phoneCategory' => ['required', 'integer'],
            'phones.*.phoneNumber' => ['required', 'string', 'max:100'],

            'selectedSkills' => ['array'],
            'selectedSkills.*' => ['integer'],
        ];
    }

    protected function messages(): array
    {
        return [
            'zip.regex' => 'Bitte nur die 4-stellige Postleitzahl eingeben, ohne Ortsname (z. B. 1010).',
        ];
    }

    public function updatedZip(string $value): void
    {
        $this->zip = $this->sanitizeZip($value);
    }

    /**
     * Falls "1010 Wien" statt "1010" eingegeben (oder als Altdatensatz
     * gespeichert) wurde: automatisch nur die führenden Ziffern übernehmen.
     * Der Ortsname wird ohnehin separat aus tb_city anhand der PLZ ermittelt
     * und muss hier nicht eingegeben werden.
     */
    private function sanitizeZip(string $value): string
    {
        if (preg_match('/^(\d{4,})\D.*$/', trim($value), $matches)) {
            return $matches[1];
        }

        return $value;
    }

    public function addEmail(): void
    {
        $this->emails[] = [
            'id' => null,
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
            'id' => null,
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

        $this->member->update([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'dateOfBirth' => $validated['dateOfBirth'] ?: null,
            'dateOfJoin' => $validated['dateOfJoin'] ?: null,
            'street' => $validated['street'] ?: null,
            'zip' => $validated['zip'] ?: null,
            'competitionMember' => $validated['competitionMember'],
            'supportingMember' => $validated['supportingMember'],
        ]);

        $this->syncEmails();
        $this->syncPhones();

        $this->member->skills()->sync(
            array_map('intval', $this->selectedSkills)
        );

        ActivityLogger::log(
            'member.updated',
            'Mitglied "' . $this->member->full_name . '" (#' . $this->member->memberID . ') wurde geändert.',
            'Member',
            $this->member->memberID
        );

        session()->flash('success', 'Mitglied wurde gespeichert.');

        return $this->redirectRoute(
            'members.show',
            ['member' => $this->member->memberID],
            navigate: true
        );
    }

    protected function syncEmails(): void
    {
        $existingIds = collect($this->emails)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $query = MemberEmail::query()
            ->where('memberID', $this->member->memberID);

        if (empty($existingIds)) {
            $query->delete();
        } else {
            $query
                ->whereNotIn(
                    (new MemberEmail)->getKeyName(),
                    $existingIds
                )
                ->delete();
        }

        foreach ($this->emails as $email) {

            if (! empty($email['id'])) {

                MemberEmail::query()
                    ->where('memberID', $this->member->memberID)
                    ->whereKey($email['id'])
                    ->update([
                        'email' => $email['email'],
                    ]);

            } else {

                MemberEmail::create([
                    'memberID' => $this->member->memberID,
                    'email' => $email['email'],
                ]);

            }
        }
    }

    protected function syncPhones(): void
    {
        $existingIds = collect($this->phones)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $query = MemberPhone::query()
            ->where('memberID', $this->member->memberID);

        if (empty($existingIds)) {
            $query->delete();
        } else {
            $query
                ->whereNotIn(
                    (new MemberPhone)->getKeyName(),
                    $existingIds
                )
                ->delete();
        }

        foreach ($this->phones as $phone) {
            if (! empty($phone['id'])) {
                MemberPhone::query()
                    ->where('memberID', $this->member->memberID)
                    ->whereKey($phone['id'])
                    ->update([
                        'phoneCategory' => $phone['phoneCategory'],
                        'phoneNumber' => $phone['phoneNumber'],
                    ]);
            } else {
                MemberPhone::create([
                    'memberID' => $this->member->memberID,
                    'phoneCategory' => $phone['phoneCategory'],
                    'phoneNumber' => $phone['phoneNumber'],
                ]);
            }
        }
    }

    public function render()
    {
        return view('livewire.members.edit', [
            'skills' => Skill::where('active', true)->orderBy('name')->get(),
        ])
            ->layout('layouts.app', [
                'title' => 'Mitglied bearbeiten | VEMA',
                'heading' => 'Mitglied bearbeiten',
            ]);
    }
}
