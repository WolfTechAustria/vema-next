<?php

namespace App\Services;

use App\Models\ActivityLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

class ActivityLogger
{
    /**
     * Protokolliert ein Ereignis. Ermittelt den handelnden Akteur selbst —
     * genau ein Guard ist je Request aktiv, die jeweils andere Spalte bleibt
     * leer. Optional mit den geänderten Feldern (siehe changes()).
     *
     * @param  list<array{field: string, old: ?string, new: ?string, masked: bool}>|null  $changes
     */
    public static function log(
        string $action,
        string $description,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $changes = null,
    ): void {
        ActivityLog::create([
            'userID' => Auth::guard('web')->id(),
            'memberAccountID' => Auth::guard('member')->id(),
            'action' => $action,
            'description' => Str::limit($description, 255, '…'),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes ?: null,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Vergleicht zwei Momentaufnahmen und liefert die geänderten Felder in
     * der Reihenfolge von $labels. Nur Schlüssel aus $labels werden
     * verglichen; Felder aus $masked erscheinen nur als „geändert“
     * (Passwörter, IBAN, geheime Links).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $labels  Schlüssel => Anzeigename
     * @param  list<string>  $masked
     * @return list<array{field: string, old: ?string, new: ?string, masked: bool}>
     */
    public static function changes(array $before, array $after, array $labels, array $masked = []): array
    {
        $changes = [];

        foreach ($labels as $key => $label) {
            $old = self::formatValue($before[$key] ?? null);
            $new = self::formatValue($after[$key] ?? null);

            if ($old === $new) {
                continue;
            }

            $isMasked = in_array($key, $masked, true);

            $changes[] = [
                'field' => $label,
                'old' => $isMasked ? null : $old,
                'new' => $isMasked ? null : $new,
                'masked' => $isMasked,
            ];
        }

        return $changes;
    }

    /**
     * Momentaufnahme einer Neuanlage: alle befüllten Felder als „neu“.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $labels
     * @param  list<string>  $masked
     * @return list<array{field: string, old: ?string, new: ?string, masked: bool}>
     */
    public static function created(array $values, array $labels, array $masked = []): array
    {
        return self::changes([], $values, $labels, $masked);
    }

    /**
     * Einheitliche Textdarstellung, damit z. B. 1 und true oder ein
     * Carbon-Datum und sein String als gleich erkannt werden.
     */
    public static function formatValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = method_exists($value, 'label') ? $value->label() : $value->value;
        } elseif ($value instanceof UnitEnum) {
            $value = $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00'
                ? $value->format('d.m.Y')
                : $value->format('d.m.Y H:i');
        }

        if (is_bool($value)) {
            return $value ? 'Ja' : 'Nein';
        }

        if (is_array($value)) {
            $items = array_values(array_filter(
                array_map(fn ($item) => self::formatValue($item), $value),
                fn ($item) => $item !== null
            ));
            sort($items);

            return $items === [] ? null : implode(', ', $items);
        }

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        // ISO-Datum aus Formularen wie ein Carbon-Datum darstellen.
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)) {
            return $match[3].'.'.$match[2].'.'.$match[1];
        }

        return $value === '' ? null : Str::limit($value, 500, '…');
    }
}
