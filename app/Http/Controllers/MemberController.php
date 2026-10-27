<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Services\BirthdayListPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function downloadOverview()
    {
        $members = Member::query()
            ->with([
                'city',
                'emails',
                'phones',
            ])
            ->where('active', 1)
            ->get()
            ->sortBy(fn ($member) => ($member->surname ?? '')
                .' '
                .($member->name ?? '')
            )
            ->values();

        $pdf = Pdf::loadView(
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
                'Content-Disposition' => 'inline; filename="Mitgliederliste_'
                    .now()->format('Y-m-d')
                    .'.pdf"',
            ]
        );
    }

    // Neue Methode innerhalb der bestehenden Klasse App\Http\Controllers\MemberController

    public function downloadBirthdaysPdf(Request $request, BirthdayListPdf $birthdayListPdf)
    {
        $month = (int) $request->query('month', now()->month);

        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }

        return response(
            $birthdayListPdf->render($month),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Geburtstagsliste_'
                    .Carbon::create()->month($month)->format('m')
                    .'_'.now()->format('Y')
                    .'.pdf"',
            ]
        );
    }
}
