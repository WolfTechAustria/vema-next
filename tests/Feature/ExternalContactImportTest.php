<?php

use App\Livewire\ExternalContacts\Index as ExternalContactsIndex;
use App\Models\ExternalContact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*
 * Import externer Kontakte aus einer Vereinsliste im Aufbau der
 * Angerberger Gemeindeliste (Titelzeile, gesperrte Überschriften,
 * "Nachname Vorname", Adresse statt eigener Felder).
 */

/**
 * @param  list<list<mixed>>  $rows
 */
function clubListUpload(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);

    $path = tempnam(sys_get_temp_dir(), 'clublist').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return UploadedFile::fake()->createWithContent('Vereinsliste.xlsx', file_get_contents($path));
}

function angerbergClubList(): UploadedFile
{
    return clubListUpload([
        ['Angerberger Vereine und Körperschaften', null, null, 'Stand vom 10/2026'],
        [],
        ['V E R E I N', 'O B M A N N / O B F R A U', 'A D R E S S E ', 'M A I L ', 'T E L E F O N N U M M E R'],
        [],
        ['  ALTHERREN', 'Lettenbichler Erwin', 'Unholzen 121', 'erdalini@aon.at', '0664 / 2338787'],
        ['Union Reitverein', 'Lechner Josef jun.', 'Linden 52', 'feldererhof@gmx.at', 56354],
        ['  LANDJUGEND ANGERBERG', 'Gastl Hannes', 'Embach 75', 'hannesgastl82@gmail.com', '0664/591307', 'jb.lj-angerberg@gmx.at '],
        ['Höhlenkunde', 'Spötl Christoph (Obmann)', null, 'christoph.spoetl@uibk.ac.at'],
        ['  OBST - UND GARTENBAUVEREIN', 'Malzer Andrea 56728', 'Kreith 3, 6321 Angath', 'info@gartenbauverein.at'],
        ['  SKATEBOARDCLUB ANGERBERG', 'Leitner Peter', 'Mitte 281'],
        [],
        ['      VEREINE  IN  DER  PFARRGEMEINDE'],
        ['Doppelt', 'Gastl Hannes', null, 'HANNESGASTL82@gmail.com'],
    ]);
}

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('previews the club list without saving anything', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(ExternalContactsIndex::class)
        ->set('showImport', true)
        ->set('importFile', angerbergClubList())
        ->assertHasNoErrors()
        ->assertSee('5 neu')
        ->assertSee('Keine E-Mail-Adresse')
        ->assertSee('E-Mail bereits in Zeile 7');

    expect(collect($component->get('importPreview'))->pluck('status', 'line')->all())->toBe([
        5 => 'create',
        6 => 'create',
        7 => 'create',
        8 => 'create',
        9 => 'create',
        10 => 'skip',
        13 => 'skip',
    ])->and(ExternalContact::count())->toBe(0);
});

it('imports the club list into external contacts', function () {
    Livewire::actingAs($this->admin)
        ->test(ExternalContactsIndex::class)
        ->set('showImport', true)
        ->set('importFile', angerbergClubList())
        ->call('runImport')
        ->assertHasNoErrors()
        ->assertSet('showImport', false)
        ->assertSee('Import abgeschlossen: 5 angelegt, 0 aktualisiert, 2 übersprungen.');

    expect(ExternalContact::count())->toBe(5);

    $erwin = ExternalContact::query()->where('email', 'erdalini@aon.at')->sole();

    expect($erwin->surname)->toBe('Lettenbichler')
        ->and($erwin->name)->toBe('Erwin')
        ->and($erwin->organization)->toBe('ALTHERREN')
        ->and($erwin->phone)->toBe('0664 / 2338787')
        ->and($erwin->note)->toBe('Adresse: Unholzen 121')
        ->and($erwin->active)->toBeTrue();

    $josef = ExternalContact::query()->where('email', 'feldererhof@gmx.at')->sole();

    expect($josef->name)->toBe('Josef jun.')
        ->and($josef->phone)->toBe('56354');

    expect(ExternalContact::query()->where('email', 'hannesgastl82@gmail.com')->value('note'))
        ->toBe("Adresse: Embach 75\nWeitere E-Mail: jb.lj-angerberg@gmx.at");

    $christoph = ExternalContact::query()->where('email', 'christoph.spoetl@uibk.ac.at')->sole();

    expect($christoph->name)->toBe('Christoph')
        ->and($christoph->note)->toBe('Obmann');

    expect(ExternalContact::query()->where('email', 'info@gartenbauverein.at')->value('name'))
        ->toBe('Andrea');
});

it('updates contacts that already exist without overwriting their notes', function () {
    ExternalContact::create([
        'name' => 'E.',
        'surname' => 'Lettenbichler',
        'email' => 'Erdalini@aon.at',
        'note' => 'Eigene Notiz',
        'active' => false,
    ]);

    Livewire::actingAs($this->admin)
        ->test(ExternalContactsIndex::class)
        ->set('showImport', true)
        ->set('importFile', angerbergClubList())
        ->assertSee('1 aktualisieren')
        ->call('runImport')
        ->assertSee('Import abgeschlossen: 4 angelegt, 1 aktualisiert, 2 übersprungen.');

    $erwin = ExternalContact::query()->where('surname', 'Lettenbichler')->sole();

    expect($erwin->name)->toBe('Erwin')
        ->and($erwin->organization)->toBe('ALTHERREN')
        ->and($erwin->note)->toBe('Eigene Notiz')
        ->and($erwin->active)->toBeFalse();
});

it('supports separate first and last name columns', function () {
    Livewire::actingAs($this->admin)
        ->test(ExternalContactsIndex::class)
        ->set('importFile', clubListUpload([
            ['Vorname', 'Nachname', 'Firma', 'E-Mail', 'Tel.'],
            ['Anna', 'Muster', 'Muster GmbH', 'anna@muster.at', '0664 1234567'],
        ]))
        ->call('runImport')
        ->assertHasNoErrors();

    $anna = ExternalContact::query()->sole();

    expect($anna->name)->toBe('Anna')
        ->and($anna->surname)->toBe('Muster')
        ->and($anna->organization)->toBe('Muster GmbH')
        ->and($anna->phone)->toBe('0664 1234567');
});

it('rejects a list without recognisable columns', function () {
    Livewire::actingAs($this->admin)
        ->test(ExternalContactsIndex::class)
        ->set('importFile', clubListUpload([
            ['Spalte A', 'Spalte B'],
            ['foo', 'bar'],
        ]))
        ->assertHasErrors('importFile')
        ->assertSet('importPreview', []);

    expect(ExternalContact::count())->toBe(0);
});
