<?php

use Illuminate\Support\Facades\DB;

/*
 * Die Tests laufen unter SQLite, das beliebig lange Indexnamen erlaubt.
 * MySQL bricht ab 64 Zeichen mit „Identifier name … is too long“ ab —
 * erst beim Migrieren auf dem Server. Deshalb hier vorab prüfen.
 */
it('keeps every index name within the MySQL limit of 64 characters', function () {
    $tooLong = collect(DB::select("select name from sqlite_master where type = 'index' and name not like 'sqlite_autoindex%'"))
        ->pluck('name')
        ->filter(fn (string $name) => strlen($name) > 64)
        ->values()
        ->all();

    expect($tooLong)->toBe([]);
});
