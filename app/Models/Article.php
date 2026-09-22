<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    protected $table = 'tb_articles';

    protected $primaryKey = 'articleID';

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'price_net' => 'decimal:2',
        'tax_rate' => 'decimal:2',
    ];

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'articleID', 'articleID');
    }

    /**
     * Findet einen aktiven Artikel anhand des Namens (trim + case-insensitiv),
     * damit beim Speichern einer Freitextposition nicht wegen
     * Groß-/Kleinschreibung oder Leerzeichen unnötig Duplikate entstehen.
     */
    public static function findByName(string $name): ?self
    {
        $normalized = trim($name);

        if ($normalized === '') {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($normalized)])
            ->first();
    }
}
