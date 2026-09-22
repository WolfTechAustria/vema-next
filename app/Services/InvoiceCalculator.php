<?php

namespace App\Services;

/**
 * Reine Rechenlogik für Rechnungspositionen und -summen. Wird sowohl beim
 * Speichern der Positionen als auch für die Gesamtsummen der Rechnung
 * verwendet, damit die im JS berechnete Live-Vorschau nie die tatsächlich
 * gespeicherten (autoritativen) Werte ist.
 */
class InvoiceCalculator
{
    /**
     * @return array{net: float, gross: float}
     */
    public static function lineTotals(
        float $quantity,
        float $priceNet,
        float $discountPercent,
        float $taxRate,
        bool $smallBusinessNoVat
    ): array {
        $net = round($quantity * $priceNet * (1 - $discountPercent / 100), 2);

        $gross = $smallBusinessNoVat
            ? $net
            : round($net * (1 + $taxRate / 100), 2);

        return [
            'net' => $net,
            'gross' => $gross,
        ];
    }

    /**
     * @param array<int, array{quantity: float, price_net: float, discount_percent: float, tax_rate: float}> $lines
     * @return array{subtotal_net: float, discount_total: float, tax_total: float, total_gross: float}
     */
    public static function invoiceTotals(array $lines, bool $smallBusinessNoVat): array
    {
        $subtotalNet = 0.0;
        $discountTotal = 0.0;
        $totalGross = 0.0;

        foreach ($lines as $line) {
            $undiscountedNet = round($line['quantity'] * $line['price_net'], 2);

            $lineTotals = self::lineTotals(
                $line['quantity'],
                $line['price_net'],
                $line['discount_percent'],
                $line['tax_rate'],
                $smallBusinessNoVat
            );

            $subtotalNet += $undiscountedNet;
            $discountTotal += round($undiscountedNet - $lineTotals['net'], 2);
            $totalGross += $lineTotals['gross'];
        }

        $subtotalNet = round($subtotalNet, 2);
        $discountTotal = round($discountTotal, 2);
        $totalGross = round($totalGross, 2);

        $taxTotal = round($totalGross - ($subtotalNet - $discountTotal), 2);

        return [
            'subtotal_net' => $subtotalNet,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total_gross' => $totalGross,
        ];
    }
}
