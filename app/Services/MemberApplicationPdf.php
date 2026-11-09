<?php

namespace App\Services;

use App\Models\MemberApplication;
use App\Models\MemberField;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Ausgefüllte Beitrittserklärung einer Online-Anmeldung als PDF — wird bei
 * der Freigabe in den Dokumenten des Mitglieds abgelegt.
 */
class MemberApplicationPdf
{
    public function __construct(
        private PdfLetterheadService $letterheadService,
        private MemberApplicationSettings $settings,
        private MemberFields $memberFields,
    ) {}

    public function render(MemberApplication $application): string
    {
        $pdf = Pdf::loadView('pdf.member-application', [
            'application' => $application,
            'clubName' => Setting::current()->name,
            'intro' => $this->settings->intro(),
            'privacyText' => $this->settings->privacyText(),
            'rows' => $this->rows($application),
            'membershipInfo' => collect($this->settings->membershipTypes())
                ->firstWhere('label', $application->membership_type)['info'] ?? null,
            'signature' => $application->signatureDataUrl(),
        ])->setPaper('a4', 'portrait');

        return $this->letterheadService->applyListBackground($pdf->output());
    }

    public function filename(MemberApplication $application): string
    {
        return 'beitrittserklaerung-'.str($application->full_name)->slug().'.pdf';
    }

    /**
     * Ausgefüllte Angaben zur Person: Bezeichnung => Wert.
     *
     * @return array<string, string>
     */
    public function rows(MemberApplication $application): array
    {
        $rows = [
            'Anrede' => match ($application->gender) {
                'm' => 'Herr',
                'w' => 'Frau',
                default => null,
            },
            'Name' => $application->full_name,
            'Geburtsdatum' => $application->dateOfBirth?->format('d.m.Y'),
            'Adresse' => trim(($application->street ? $application->street.', ' : '').trim($application->zip.' '.$application->city), ', ') ?: null,
            'E-Mail' => $application->email,
            'Telefon' => $application->phone,
        ];

        $values = $application->custom_fields ?? [];

        if ($values !== []) {
            MemberField::query()
                ->whereIn('fieldID', array_map('intval', array_keys($values)))
                ->ordered()
                ->get()
                ->each(function (MemberField $field) use (&$rows, $values) {
                    $rows[$field->name] = $this->memberFields->display($field, (string) $values[$field->fieldID]);
                });
        }

        return array_filter($rows, fn (?string $value) => filled($value));
    }
}
