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
}
