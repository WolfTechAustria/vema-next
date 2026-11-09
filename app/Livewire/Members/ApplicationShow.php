<?php

namespace App\Livewire\Members;

use App\Enums\MemberApplicationStatus;
use App\Enums\MembershipKind;
use App\Models\Member;
use App\Models\MemberApplication;
use App\Services\ActivityLogger;
use App\Services\MemberApplicationPdf;
use App\Services\MemberApplications;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Einzelne Online-Anmeldung prüfen, korrigieren, freigeben oder ablehnen.
 */
class ApplicationShow extends Component
{
    #[Locked]
    public MemberApplication $application;

    public bool $editing = false;

    public string $gender = '';

    public string $name = '';

    public string $surname = '';

    public string $dateOfBirth = '';

    public string $street = '';

    public string $zip = '';

    public string $city = '';

    public string $email = '';

    public string $phone = '';

    public string $membershipKind = '';

    public bool $sendWelcomeMail = true;

    public bool $showReject = false;

    public string $rejectionNote = '';

    public bool $notifyApplicant = true;

    public function mount(MemberApplication $application): void
    {
        $this->application = $application;
        $this->fillForm();
    }

    private function fillForm(): void
    {
        $application = $this->application;

        $this->gender = (string) $application->gender;
        $this->name = $application->name;
        $this->surname = $application->surname;
        $this->dateOfBirth = (string) $application->dateOfBirth?->toDateString();
        $this->street = (string) $application->street;
        $this->zip = (string) $application->zip;
        $this->city = (string) $application->city;
        $this->email = $application->email;
        $this->phone = (string) $application->phone;
        $this->membershipKind = (string) $application->membership_kind?->value;
    }

    public function edit(): void
    {
        $this->ensureOpen();
        $this->fillForm();
        $this->resetValidation();
        $this->editing = true;
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
        $this->fillForm();
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->ensureOpen();

        $validated = $this->validate([
            'gender' => ['nullable', Rule::in(['m', 'w'])],
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'dateOfBirth' => ['nullable', 'date_format:Y-m-d'],
            'street' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'regex:/^\d{4,5}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'membershipKind' => ['nullable', Rule::enum(MembershipKind::class)],
        ], [
            'zip.regex' => 'Bitte eine gültige Postleitzahl eingeben.',
        ]);

        $before = $this->application->activitySnapshot();

        $this->application->update([
            'gender' => $validated['gender'] ?: null,
            'name' => trim($validated['name']),
            'surname' => trim($validated['surname']),
            'dateOfBirth' => $validated['dateOfBirth'] ?: null,
            'street' => trim($validated['street']) ?: null,
            'zip' => $validated['zip'] ?: null,
            'city' => trim($validated['city']) ?: null,
            'email' => trim($validated['email']),
            'phone' => trim($validated['phone']) ?: null,
            'membership_kind' => $validated['membershipKind'] ?: null,
        ]);

        $changes = ActivityLogger::changes($before, $this->application->activitySnapshot(), MemberApplication::ACTIVITY_LABELS);

        if ($changes !== []) {
            ActivityLogger::log(
                'member_application.updated',
                'Online-Anmeldung von "'.$this->application->full_name.'" wurde korrigiert.',
                'MemberApplication',
                $this->application->applicationID,
                $changes
            );
        }

        $this->editing = false;

        session()->flash('success', 'Änderungen wurden gespeichert.');
    }

    public function approve(MemberApplications $applications)
    {
        try {
            $member = $applications->approve($this->application, $this->sendWelcomeMail);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return null;
        }

        session()->flash('success', 'Anmeldung wurde freigegeben, „'.$member->full_name.'“ ist jetzt Mitglied.');

        return $this->redirectRoute('members.show', ['member' => $member->memberID], navigate: true);
    }

    public function reject(MemberApplications $applications)
    {
        $this->ensureOpen();

        $this->validate(['rejectionNote' => ['nullable', 'string', 'max:2000']]);

        $applications->reject($this->application, $this->rejectionNote, $this->notifyApplicant);

        session()->flash('success', 'Anmeldung wurde abgelehnt.');

        return $this->redirectRoute('members.applications.index', navigate: true);
    }

    public function delete(MemberApplications $applications)
    {
        $applications->delete($this->application);

        session()->flash('success', 'Anmeldung wurde gelöscht.');

        return $this->redirectRoute('members.applications.index', navigate: true);
    }

    private function ensureOpen(): void
    {
        abort_unless($this->application->isOpen(), 403);
    }

    /**
     * Bestehende Mitglieder mit gleichem Namen oder gleicher E-Mail-Adresse.
     *
     * @return Collection<int, Member>
     */
    private function possibleDuplicates(): Collection
    {
        if (! $this->application->isOpen()) {
            return collect();
        }

        return Member::query()
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('name', $this->application->name)
                        ->where('surname', $this->application->surname);
                })->orWhereHas('emails', fn ($query) => $query->where('email', $this->application->email));
            })
            ->orderBy('surname')
            ->limit(5)
            ->get();
    }

    public function render(MemberApplicationPdf $pdf)
    {
        return view('livewire.members.application-show', [
            'rows' => $pdf->rows($this->application),
            'duplicates' => $this->possibleDuplicates(),
            'kinds' => MembershipKind::cases(),
            'signature' => $this->application->signatureDataUrl(),
            'isOpen' => $this->application->status === MemberApplicationStatus::Pending,
        ])->layout('layouts.app', [
            'title' => 'Online-Anmeldung | VEMA',
            'heading' => 'Mitglieder',
        ]);
    }
}
