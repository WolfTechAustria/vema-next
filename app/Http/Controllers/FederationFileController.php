<?php

namespace App\Http\Controllers;

use App\Models\FederationFile;
use App\Services\TenantManager;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Dokumente und Anhänge der Verbandszentrale — für den Verband selbst und
 * seine aktiven Mitgliedsvereine.
 */
class FederationFileController extends Controller
{
    public function show(FederationFile $federationFile, TenantManager $tenantManager): BinaryFileResponse
    {
        abort_unless($federationFile->isAccessibleFor($tenantManager->current()), 403);
        abort_unless(is_file($federationFile->absolutePath()), 404);

        return response()->file($federationFile->absolutePath(), [
            'Content-Type' => $federationFile->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addcslashes($federationFile->file_name, '"\\').'"',
        ]);
    }
}
