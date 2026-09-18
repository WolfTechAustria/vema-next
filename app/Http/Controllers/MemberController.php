<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function downloadOverview()
    {
        $members = \App\Models\Member::query()
            ->with([
                'city',
                'emails',
                'phones',
            ])
            ->where('active', 1)
            ->get()
            ->sortBy(fn ($member) =>
                ($member->surname ?? '')
                . ' '
                . ($member->name ?? '')
            )
            ->values();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.members-overview',
            [
                'members' => $members,
            ]
        )->setPaper('a4', 'landscape');

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();

        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();

        $font = $fontMetrics->getFont(
            'DejaVu Sans',
            'normal'
        );

        $canvas->page_text(
            730,
            565,
            'Seite {PAGE_NUM} / {PAGE_COUNT}',
            $font,
            7,
            [0.4, 0.4, 0.4]
        );

        return response(
            $pdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'inline; filename="Mitgliederliste_'
                    . now()->format('Y-m-d')
                    . '.pdf"',
            ]
        );
    }

    // Neue Methode innerhalb der bestehenden Klasse App\Http\Controllers\MemberController

    public function downloadBirthdaysPdf(\Illuminate\Http\Request $request)
    {
        $month = (int) $request->query('month', now()->month);

        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }

        $members = \App\Models\Member::query()
            ->active()
            ->whereNotNull('dateOfBirth')
            ->whereMonth('dateOfBirth', $month)
            ->orderByRaw('DAY(dateOfBirth)')
            ->orderBy('surname')
            ->orderBy('name')
            ->get()
            ->map(function (\App\Models\Member $member) {
                $age = $member->ageOn(now());

                return [
                    'member' => $member,
                    'age' => $age,
                    'isRound' => $member->isRoundBirthday($age),
                    'isHalfRound' => $member->isHalfRoundBirthday($age),
                ];
            });

        $monthName = now()
            ->month($month)
            ->translatedFormat('F Y');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'pdf.members-birthdays',
            [
                'members' => $members,
                'monthName' => $monthName,
            ]
        )->setPaper('a4', 'portrait');

        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();

        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();

        $font = $fontMetrics->getFont(
            'DejaVu Sans',
            'normal'
        );

        $canvas->page_text(
            270,
            810,
            'Seite {PAGE_NUM} / {PAGE_COUNT}',
            $font,
            7,
            [0.4, 0.4, 0.4]
        );

        return response(
            $pdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' =>
                    'inline; filename="Geburtstagsliste_'
                    . \Carbon\Carbon::create()->month($month)->format('m')
                    . '_' . now()->format('Y')
                    . '.pdf"',
            ]
        );
    }
}
