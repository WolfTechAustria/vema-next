<?php

namespace App\Http\Controllers;

use App\Models\MemberApplication;
use App\Services\MemberApplicationPdf;
use Illuminate\Http\Response;

class MemberApplicationController extends Controller
{
    /**
     * Ausgefüllte Beitrittserklärung einer Online-Anmeldung als PDF.
     */
    public function pdf(MemberApplication $application, MemberApplicationPdf $pdf): Response
    {
        return response($pdf->render($application), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($application).'"',
        ]);
    }
}
