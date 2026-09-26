<?php

namespace App\Models;

use App\Enums\DemoResetMode;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'tb_settings';

    protected $primaryKey = 'settingsID';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'demo_enabled' => 'boolean',
            'demo_reset_mode' => DemoResetMode::class,
            'demo_last_reset_at' => 'datetime',
            'imap_port' => 'integer',
            'imap_validate_cert' => 'boolean',
            'imap_password' => 'encrypted',
        ];
    }

    /**
     * Ort in der Datumszeile von Briefen („Angerberg, am …“).
     */
    public function letterPlace(): string
    {
        return $this->letter_place ?: ($this->city ?? '');
    }

    /**
     * Datumszeile für Briefe, z. B. „Angerberg, am 26.09.2026“.
     */
    public function letterDateLine(): string
    {
        $date = 'am '.now()->format('d.m.Y');
        $place = $this->letterPlace();

        return $place !== '' ? $place.', '.$date : $date;
    }

    /**
     * Empfängergruppe für Geburtstags-Benachrichtigungen; ohne eigene
     * Einstellung gilt der Wert aus config/birthday.php.
     */
    public function birthdayRecipientGroupName(): string
    {
        return $this->birthday_recipient_group ?: (string) config('birthday.recipient_group');
    }

    /**
     * Eigenes IMAP-Konto nur, wenn ein Host hinterlegt ist — sonst gilt die
     * Konfiguration aus .env (config/imap.php).
     */
    public function hasOwnImapAccount(): bool
    {
        return filled($this->imap_host);
    }

    /**
     * Es gibt genau eine Einstellungen-Zeile für den ganzen Verein.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'name' => 'VEMA',
        ]);
    }
}
