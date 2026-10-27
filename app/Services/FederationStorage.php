<?php

namespace App\Services;

use App\Models\FederationFile;
use App\Models\FederationMessage;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Dateien der Verbandszentrale: immer im privaten Speicher des Verbands
 * (auch im Testmodus), damit die Mitgliedsvereine sie finden.
 */
class FederationStorage
{
    public function store(Tenant $federation, UploadedFile $file, string $directory, string $title, ?FederationMessage $message = null): FederationFile
    {
        $disk = Storage::build([
            'driver' => 'local',
            'root' => $federation->storagePath('private'),
        ]);

        return $federation->federationFiles()->create([
            'federation_message_id' => $message?->id,
            'title' => $title,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $disk->putFile($directory, $file),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }
}
