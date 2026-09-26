<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class PdfLetterheadService
{
    public function __construct(
        public ClubBranding $branding,
    ) {}

    /**
     * Legt das Briefpapier des Vereins unter jede Seite des übergebenen PDFs
     * (Inhalt als String, z. B. DomPDF-output()). Ohne hinterlegtes
     * Briefpapier wird das PDF unverändert zurückgegeben.
     */
    public function applyClubLetterhead(string $contentPdf): string
    {
        $letterheadPdf = $this->branding->letterheadPath();

        if ($letterheadPdf === null) {
            return $contentPdf;
        }

        return $this->apply(
            StreamReader::createByString($contentPdf),
            $letterheadPdf
        );
    }

    /**
     * @param  string|StreamReader  $contentPdf  Dateipfad oder PDF-Stream
     */
    public function apply(
        string|StreamReader $contentPdf,
        string $letterheadPdf
    ): string {
        $output = new Fpdi;

        /*
         * Briefpapier laden.
         */
        $pageCount = $output->setSourceFile($letterheadPdf);

        if ($pageCount < 1) {
            throw new \RuntimeException(
                'Briefpapier enthält keine PDF-Seite.'
            );
        }

        $letterheadTemplate = $output->importPage(1);

        /*
         * Inhalt laden.
         */
        $contentPages = $output->setSourceFile($contentPdf);

        for ($page = 1; $page <= $contentPages; $page++) {

            $pageTemplate = $output->importPage($page);

            $size = $output->getTemplateSize($pageTemplate);

            $orientation =
                $size['width'] > $size['height']
                    ? 'L'
                    : 'P';

            $output->AddPage(
                $orientation,
                [
                    $size['width'],
                    $size['height'],
                ]
            );

            /*
             * Briefpapier zuerst, Inhalt darüber.
             */
            $output->useTemplate(
                $letterheadTemplate,
                0,
                0,
                $size['width'],
                $size['height']
            );

            $output->useTemplate(
                $pageTemplate,
                0,
                0,
                $size['width'],
                $size['height']
            );
        }

        return $output->Output(
            'S'
        );
    }
}
