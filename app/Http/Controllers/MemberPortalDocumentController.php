<?php

namespace App\Http\Controllers;

use App\Models\Circular;
use App\Models\CircularAttachment;
use App\Models\MembershipFeePrescription;
use App\Services\ActiveMemberResolver;
use App\Services\CircularPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reine Lesezugriffe des Mitgliederbereichs auf eigene Dokumente.
 *
 * Jede Aktion prüft, dass das Dokument dem aktiven Mitglied gehört.
 */
class MemberPortalDocumentController extends Controller
{
    public function circularPdf(
        Circular $circular,
        ActiveMemberResolver $activeMemberResolver,
        CircularPdfService $circularPdfService
    ): Response {
        $member = $activeMemberResolver->resolve();

        $this->ensureCircularWasSentTo($circular, $member->memberID);

        return response(
            $circularPdfService->renderForMember($circular, $member),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$circularPdfService->fileNameFor($circular, $member).'"',
            ]
        );
    }

    public function circularAttachment(
        CircularAttachment $attachment,
        ActiveMemberResolver $activeMemberResolver
    ): StreamedResponse {
        $member = $activeMemberResolver->resolve();

        $this->ensureCircularWasSentTo($attachment->circular, $member->memberID);

        abort_unless(
            $attachment->file_path
            && Storage::disk('local')->exists($attachment->file_path),
            404
        );

        return Storage::disk('local')->response(
            $attachment->file_path,
            $attachment->file_name
        );
    }

    public function feeDocument(
        MembershipFeePrescription $prescription,
        ActiveMemberResolver $activeMemberResolver
    ): Response {
        $member = $activeMemberResolver->resolve();

        abort_unless(
            (int) $prescription->memberID === (int) $member->memberID
            && $prescription->sent_at !== null,
            404
        );

        $disk = Storage::disk('local');

        abort_unless(
            $prescription->file_path && $disk->exists($prescription->file_path),
            404,
            'PDF-Datei nicht gefunden.'
        );

        return response(
            $disk->get($prescription->file_path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$prescription->file_name.'"',
            ]
        );
    }

    private function ensureCircularWasSentTo(?Circular $circular, int $memberID): void
    {
        abort_unless(
            $circular
            && $circular->recipients()
                ->where('memberID', $memberID)
                ->whereNotNull('sent_at')
                ->exists(),
            404
        );
    }
}
