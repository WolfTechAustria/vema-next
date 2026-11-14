<?php

namespace App\Http\Controllers;

use App\Models\SepaDebitRun;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SepaDebitController extends Controller
{
    /**
     * SEPA-XML-Datei eines Lastschrift-Laufs für den Upload ins Online-Banking.
     */
    public function download(SepaDebitRun $run): StreamedResponse
    {
        abort_if($run->status === SepaDebitRun::STATUS_CANCELLED || ! $run->file_path || ! Storage::disk('local')->exists($run->file_path), 404);

        return Storage::disk('local')->download(
            $run->file_path,
            'sepa-lastschrift-'.$run->collection_date->format('Y-m-d').'-'.$run->runID.'.xml',
            ['Content-Type' => 'application/xml']
        );
    }
}
