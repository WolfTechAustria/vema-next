<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class PdfLetterheadService
{
    public function apply(
        string $contentPdf,
        string $letterheadPdf
    ): string {
        $output = new Fpdi();

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
         * Beitragsvorschreibung laden.
         */
        $content = new Fpdi();

        $contentPages = $content->setSourceFile(
            $contentPdf
        );

        for ($page = 1; $page <= $contentPages; $page++) {

            $contentTemplate =
                $content->importPage($page);

            $size = $content->getTemplateSize(
                $contentTemplate
            );

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
             * Briefpapier zuerst.
             */
            $output->useTemplate(
                $letterheadTemplate,
                0,
                0,
                $size['width'],
                $size['height']
            );

            /*
             * Inhalt darüber.
             */
            $output->setSourceFile(
                $contentPdf
            );

            $pageTemplate =
                $output->importPage($page);

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
