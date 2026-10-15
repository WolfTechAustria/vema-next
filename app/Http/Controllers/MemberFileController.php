<?php

namespace App\Http\Controllers;

use App\Models\MemberFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberFileController extends Controller
{
    /**
     * Dokument eines Mitglieds für Funktionäre — inline (Vorschau) oder mit
     * ?download=1 als Download.
     */
    public function show(MemberFile $memberFile, Request $request): StreamedResponse
    {
        return self::respond($memberFile, $request->boolean('download'));
    }

    public static function respond(MemberFile $memberFile, bool $download = false): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($memberFile->file_path && $disk->exists($memberFile->file_path), 404, 'Datei nicht gefunden.');

        $fileName = self::downloadName($memberFile);
        $headers = ['X-Content-Type-Options' => 'nosniff'];

        return $download
            ? $disk->download($memberFile->file_path, $fileName, $headers)
            : $disk->response($memberFile->file_path, $fileName, $headers);
    }

    /**
     * Anzeigename samt ursprünglicher Endung (der Name ist frei änderbar).
     */
    private static function downloadName(MemberFile $memberFile): string
    {
        $extension = pathinfo($memberFile->original_name, PATHINFO_EXTENSION);

        if ($extension === '' || str_ends_with(mb_strtolower($memberFile->name), '.'.mb_strtolower($extension))) {
            return $memberFile->name;
        }

        return $memberFile->name.'.'.$extension;
    }
}
