<?php

namespace App\Services;

use App\Models\ExternalContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use Throwable;

/**
 * Importiert externe Kontakte aus einer Excel- oder CSV-Liste (z. B. die
 * Vereinsliste der Gemeinde). Die Kopfzeile wird anhand der Spaltennamen
 * gesucht, Namen im Format "Nachname Vorname" werden aufgeteilt und Angaben
 * ohne eigene Spalte (Adresse, Zusätze, weitere E-Mails) landen in der Notiz.
 * Kontakte mit bereits vorhandener E-Mail-Adresse werden aktualisiert.
 */
class ExternalContactImporter
{
    /**
     * Feld → Schlüsselwörter der Spaltenüberschrift (klein, nur Buchstaben).
     * Die Reihenfolge zählt: "vorname" muss vor "name" geprüft werden.
     *
     * @var array<string, list<string>>
     */
    private const COLUMN_KEYWORDS = [
        'first_name' => ['vorname'],
        'last_name' => ['nachname', 'familienname'],
        'email' => ['mail'],
        'phone' => ['telefon', 'tel', 'handy', 'mobil'],
        'address' => ['adresse', 'anschrift', 'strasse', 'straße'],
        'organization' => ['verein', 'organisation', 'firma', 'körperschaft', 'institution'],
        'full_name' => ['obmann', 'obfrau', 'ansprechpartner', 'kontakt', 'name'],
    ];

    /**
     * Wie viele Zeilen am Dateianfang nach der Kopfzeile durchsucht werden.
     */
    private const HEADER_SEARCH_ROWS = 20;

    /**
     * Liest die Datei und ermittelt je Zeile, ob ein Kontakt angelegt,
     * aktualisiert oder übersprungen wird. Es wird nichts gespeichert.
     *
     * @return list<array{
     *     line: int,
     *     status: 'create'|'update'|'skip',
     *     reason: ?string,
     *     existing_id: ?int,
     *     attributes: array{name: string, surname: string, organization: ?string, email: ?string, phone: ?string, note: ?string}
     * }>
     *
     * @throws InvalidArgumentException
     */
    public function analyze(string $path): array
    {
        $rows = $this->readRows($path);

        [$headerIndex, $columns] = $this->detectHeader($rows);

        $existingContactIds = ExternalContact::query()
            ->pluck('externalContactID', 'email')
            ->mapWithKeys(fn ($id, $email) => [Str::lower(trim((string) $email)) => (int) $id]);

        $seenEmails = [];
        $result = [];

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $row) {
            $attributes = $this->mapRow($row, $columns);

            if ($attributes === null) {
                continue;
            }

            $entry = [
                'line' => $index + 1,
                'status' => 'create',
                'reason' => null,
                'existing_id' => null,
                'attributes' => $attributes,
            ];

            $emailKey = Str::lower((string) $attributes['email']);

            if ($attributes['surname'] === '') {
                $entry['status'] = 'skip';
                $entry['reason'] = 'Kein Name';
            } elseif ($attributes['email'] === null) {
                $entry['status'] = 'skip';
                $entry['reason'] = 'Keine E-Mail-Adresse';
            } elseif (filter_var($attributes['email'], FILTER_VALIDATE_EMAIL) === false) {
                $entry['status'] = 'skip';
                $entry['reason'] = 'Ungültige E-Mail-Adresse';
            } elseif (isset($seenEmails[$emailKey])) {
                $entry['status'] = 'skip';
                $entry['reason'] = 'E-Mail bereits in Zeile '.$seenEmails[$emailKey];
            } elseif ($existingContactIds->has($emailKey)) {
                $entry['status'] = 'update';
                $entry['existing_id'] = $existingContactIds->get($emailKey);
            }

            if ($entry['status'] !== 'skip') {
                $seenEmails[$emailKey] = $entry['line'];
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Speichert die von analyze() ermittelten Zeilen.
     *
     * @param  list<array{status: string, existing_id: ?int, attributes: array<string, ?string>}>  $entries
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(array $entries): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($entries, &$summary) {
            foreach ($entries as $entry) {
                if ($entry['status'] === 'create') {
                    ExternalContact::create([
                        ...$entry['attributes'],
                        'active' => true,
                    ]);

                    $summary['created']++;
                } elseif ($entry['status'] === 'update') {
                    $contact = ExternalContact::findOrFail($entry['existing_id']);
                    $attributes = $entry['attributes'];

                    // Eigene Notizen zum Kontakt nicht überschreiben.
                    if (filled($contact->note)) {
                        unset($attributes['note']);
                    }

                    $contact->update($attributes);

                    $summary['updated']++;
                } else {
                    $summary['skipped']++;
                }
            }
        });

        return $summary;
    }

    /**
     * @return array<int, list<mixed>>
     */
    private function readRows(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path, IReader::READ_DATA_ONLY);
        } catch (Throwable) {
            throw new InvalidArgumentException('Die Datei konnte nicht gelesen werden. Erlaubt sind Excel- (.xlsx, .xls) und CSV-Dateien.');
        }

        return $spreadsheet->getSheet(0)->toArray(null, true, false, false);
    }

    /**
     * Sucht die erste Zeile, in der eine E-Mail-Spalte und eine Namensspalte
     * vorkommen, und ordnet jeder Spalte ein Feld zu.
     *
     * @param  array<int, list<mixed>>  $rows
     * @return array{0: int, 1: array<int, string>}
     */
    private function detectHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, self::HEADER_SEARCH_ROWS, true) as $index => $row) {
            $columns = [];

            foreach ($row as $columnIndex => $cell) {
                $field = $this->fieldForHeading((string) $cell);

                if ($field !== null && ! in_array($field, $columns, true)) {
                    $columns[$columnIndex] = $field;
                }
            }

            $hasName = in_array('full_name', $columns, true) || in_array('last_name', $columns, true);

            if ($hasName && in_array('email', $columns, true)) {
                return [$index, $columns];
            }
        }

        throw new InvalidArgumentException('Keine Kopfzeile gefunden. Die Liste braucht mindestens die Spalten „Name“ (oder „Obmann“) und „E-Mail“.');
    }

    private function fieldForHeading(string $heading): ?string
    {
        $normalized = preg_replace('/[^\p{L}]/u', '', Str::lower($heading));

        if ($normalized === '') {
            return null;
        }

        foreach (self::COLUMN_KEYWORDS as $field => $keywords) {
            foreach ($keywords as $keyword) {
                // Kurze Kürzel wie "Tel" nur exakt, sonst träfe es z. B. "Stellvertreter".
                $matches = mb_strlen($keyword) <= 3
                    ? $normalized === $keyword
                    : str_contains($normalized, $keyword);

                if ($matches) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<int, string>  $columns
     * @return array{name: string, surname: string, organization: ?string, email: ?string, phone: ?string, note: ?string}|null
     */
    private function mapRow(array $row, array $columns): ?array
    {
        $values = [];
        $extraValues = [];

        foreach ($row as $columnIndex => $cell) {
            $value = $this->clean($cell);

            if ($value === '') {
                continue;
            }

            if (isset($columns[$columnIndex])) {
                $values[$columns[$columnIndex]] = $value;
            } else {
                $extraValues[] = $value;
            }
        }

        $hasPerson = isset($values['full_name']) || isset($values['last_name']) || isset($values['email']);

        // Leerzeilen und Zwischenüberschriften (nur ein Vereinsname) auslassen.
        if (! $hasPerson) {
            return null;
        }

        $notes = [];

        if (isset($values['full_name'])) {
            [$surname, $name, $nameNotes] = $this->splitFullName($values['full_name']);
            $notes = $nameNotes;
        } else {
            $surname = $values['last_name'] ?? '';
            $name = $values['first_name'] ?? '';
        }

        [$email, $furtherEmails] = $this->splitEmails($values['email'] ?? '');

        foreach ($furtherEmails as $furtherEmail) {
            $notes[] = 'Weitere E-Mail: '.$furtherEmail;
        }

        if (isset($values['address'])) {
            $notes[] = 'Adresse: '.$values['address'];
        }

        foreach ($extraValues as $extraValue) {
            $notes[] = filter_var($extraValue, FILTER_VALIDATE_EMAIL)
                ? 'Weitere E-Mail: '.$extraValue
                : $extraValue;
        }

        return [
            'name' => Str::limit($name, 150, ''),
            'surname' => Str::limit($surname, 150, ''),
            'organization' => isset($values['organization']) ? Str::limit($values['organization'], 200, '') : null,
            'email' => $email,
            'phone' => isset($values['phone']) ? Str::limit($values['phone'], 100, '') : null,
            'note' => $notes === [] ? null : implode("\n", $notes),
        ];
    }

    /**
     * "Lechner Josef jun." → ["Lechner", "Josef jun.", []]. Klammerzusätze und
     * weitere Personen ("Osl Margreth/Osl Justina") kommen in die Notiz,
     * versehentlich eingetragene Telefonnummern werden entfernt.
     *
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function splitFullName(string $fullName): array
    {
        $notes = [];

        if (preg_match_all('/\(([^)]*)\)/u', $fullName, $matches)) {
            foreach ($matches[1] as $addition) {
                if (trim($addition) !== '') {
                    $notes[] = trim($addition);
                }
            }

            $fullName = preg_replace('/\([^)]*\)/u', ' ', $fullName);
        }

        $people = array_values(array_filter(array_map('trim', explode('/', $fullName))));

        foreach (array_slice($people, 1) as $furtherPerson) {
            $notes[] = 'Weitere Person: '.$furtherPerson;
        }

        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $people[0] ?? ''),
            fn (string $token) => $token !== '' && ! preg_match('/^[\d\s\/+-]+$/', $token),
        ));

        $surname = $tokens[0] ?? '';
        $name = implode(' ', array_slice($tokens, 1));

        return [$surname, $name, $notes];
    }

    /**
     * @return array{0: ?string, 1: list<string>}
     */
    private function splitEmails(string $value): array
    {
        $emails = array_values(array_filter(
            preg_split('/[\s,;]+/u', Str::lower($value)),
            fn (string $email) => $email !== '',
        ));

        return [$emails[0] ?? null, array_slice($emails, 1)];
    }

    private function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $value = str_replace("\u{00A0}", ' ', (string) $value);

        return trim(preg_replace('/\s+/u', ' ', $value), " \t\n\r\0\x0B,");
    }
}
