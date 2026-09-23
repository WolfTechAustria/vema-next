<?php

namespace App\Services;

use App\Models\Circular;
use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;

class CircularPdfService
{
    public function __construct(
        public TemplateRendererService $renderer,
        public PdfLetterheadService $letterheadService,
    ) {}

    /**
     * Erzeugt das personalisierte Rundschreiben eines Mitglieds als PDF (inkl. Briefpapier).
     */
    public function renderForMember(Circular $circular, Member $member): string
    {
        $member->loadMissing('city');

        $body = $this->renderer->circular(
            $circular->body_html ?? '',
            $member
        );

        $pdf = Pdf::loadView(
            'pdf.circular',
            [
                'circular' => $circular,
                'member' => $member,
                'body' => $body,
            ]
        )->setPaper('a4');

        $contentPath = tempnam(
            sys_get_temp_dir(),
            'circular_'
        ).'.pdf';

        file_put_contents(
            $contentPath,
            $pdf->output()
        );

        try {
            return $this->letterheadService->apply(
                $contentPath,
                storage_path('app/templates/briefpapier.pdf')
            );
        } finally {
            if (is_file($contentPath)) {
                unlink($contentPath);
            }
        }
    }

    public function fileNameFor(Circular $circular, Member $member): string
    {
        return 'Rundschreiben_'
            .$circular->circularID
            .'_'
            .$member->memberID
            .'.pdf';
    }
}
