<?php

namespace App\Services\Sepa;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use XMLWriter;

/**
 * Erzeugt eine SEPA-Lastschriftdatei (pain.008, CORE) für den Upload ins
 * Online-Banking. Je Sequenztyp (FRST/RCUR) entsteht ein eigener
 * Zahlungsblock.
 */
class Pain008Writer
{
    public const VERSION_08 = 'pain.008.001.08';

    public const VERSION_02 = 'pain.008.001.02';

    /**
     * @param  array{name: string, iban: string, bic: ?string, creditor_id: string}  $creditor
     * @param  list<array{end_to_end_id: string, amount: string|float, mandate_reference: string, mandate_signed_on: CarbonInterface, sequence_type: string, debtor_name: string, debtor_iban: string, debtor_bic: ?string, remittance: string}>  $transactions
     */
    public function write(string $messageId, CarbonInterface $collectionDate, array $creditor, array $transactions, string $version = self::VERSION_08): string
    {
        if (! in_array($version, [self::VERSION_08, self::VERSION_02], true)) {
            throw new InvalidArgumentException('Unbekannte pain-Version '.$version);
        }

        if ($transactions === []) {
            throw new InvalidArgumentException('Ein Lastschrift-Lauf braucht mindestens einen Posten.');
        }

        $bicTag = $version === self::VERSION_08 ? 'BICFI' : 'BIC';

        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('Document');
        $xml->writeAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:'.$version);
        $xml->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->startElement('CstmrDrctDbtInitn');

        $xml->startElement('GrpHdr');
        $xml->writeElement('MsgId', $this->text($messageId, 35));
        $xml->writeElement('CreDtTm', now()->format('Y-m-d\TH:i:s'));
        $xml->writeElement('NbOfTxs', (string) count($transactions));
        $xml->writeElement('CtrlSum', $this->sum($transactions));
        $xml->startElement('InitgPty');
        $xml->writeElement('Nm', $this->text($creditor['name'], 70));
        $xml->endElement();
        $xml->endElement();

        $batches = collect($transactions)->groupBy('sequence_type')->sortKeys();

        foreach ($batches as $sequenceType => $batch) {
            $batch = $batch->values()->all();

            $xml->startElement('PmtInf');
            $xml->writeElement('PmtInfId', $this->text($messageId.'-'.$sequenceType, 35));
            $xml->writeElement('PmtMtd', 'DD');
            $xml->writeElement('BtchBookg', 'true');
            $xml->writeElement('NbOfTxs', (string) count($batch));
            $xml->writeElement('CtrlSum', $this->sum($batch));

            $xml->startElement('PmtTpInf');
            $xml->startElement('SvcLvl');
            $xml->writeElement('Cd', 'SEPA');
            $xml->endElement();
            $xml->startElement('LclInstrm');
            $xml->writeElement('Cd', 'CORE');
            $xml->endElement();
            $xml->writeElement('SeqTp', (string) $sequenceType);
            $xml->endElement();

            $xml->writeElement('ReqdColltnDt', $collectionDate->format('Y-m-d'));

            $xml->startElement('Cdtr');
            $xml->writeElement('Nm', $this->text($creditor['name'], 70));
            $xml->endElement();

            $xml->startElement('CdtrAcct');
            $xml->startElement('Id');
            $xml->writeElement('IBAN', SepaValidator::normalize($creditor['iban']));
            $xml->endElement();
            $xml->endElement();

            $this->writeAgent($xml, 'CdtrAgt', $creditor['bic'] ?? null, $bicTag);

            $xml->writeElement('ChrgBr', 'SLEV');

            $xml->startElement('CdtrSchmeId');
            $xml->startElement('Id');
            $xml->startElement('PrvtId');
            $xml->startElement('Othr');
            $xml->writeElement('Id', SepaValidator::normalize($creditor['creditor_id']));
            $xml->startElement('SchmeNm');
            $xml->writeElement('Prtry', 'SEPA');
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();

            foreach ($batch as $transaction) {
                $this->writeTransaction($xml, $transaction, $bicTag);
            }

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * Nur im SEPA-Zeichensatz erlaubte Zeichen: Umlaute werden umschrieben,
     * alles andere durch Leerzeichen ersetzt.
     */
    public function text(string $value, int $maxLength): string
    {
        $value = strtr($value, ['Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', '&' => '+']);
        $value = Str::ascii($value);
        $value = (string) preg_replace("/[^A-Za-z0-9\/\-?:().,'+ ]/", ' ', $value);
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return mb_substr($value, 0, $maxLength);
    }

    /**
     * @param  array{end_to_end_id: string, amount: string|float, mandate_reference: string, mandate_signed_on: CarbonInterface, debtor_name: string, debtor_iban: string, debtor_bic: ?string, remittance: string}  $transaction
     */
    private function writeTransaction(XMLWriter $xml, array $transaction, string $bicTag): void
    {
        $xml->startElement('DrctDbtTxInf');

        $xml->startElement('PmtId');
        $xml->writeElement('EndToEndId', $this->text($transaction['end_to_end_id'], 35));
        $xml->endElement();

        $xml->startElement('InstdAmt');
        $xml->writeAttribute('Ccy', 'EUR');
        $xml->text($this->amount($transaction['amount']));
        $xml->endElement();

        $xml->startElement('DrctDbtTx');
        $xml->startElement('MndtRltdInf');
        $xml->writeElement('MndtId', $this->text($transaction['mandate_reference'], 35));
        $xml->writeElement('DtOfSgntr', $transaction['mandate_signed_on']->format('Y-m-d'));
        $xml->endElement();
        $xml->endElement();

        $this->writeAgent($xml, 'DbtrAgt', $transaction['debtor_bic'] ?? null, $bicTag);

        $xml->startElement('Dbtr');
        $xml->writeElement('Nm', $this->text($transaction['debtor_name'], 70));
        $xml->endElement();

        $xml->startElement('DbtrAcct');
        $xml->startElement('Id');
        $xml->writeElement('IBAN', SepaValidator::normalize($transaction['debtor_iban']));
        $xml->endElement();
        $xml->endElement();

        $xml->startElement('RmtInf');
        $xml->writeElement('Ustrd', $this->text($transaction['remittance'], 140));
        $xml->endElement();

        $xml->endElement();
    }

    /**
     * Bank des Kontos: mit BIC, sonst „NOTPROVIDED“ (IBAN-only).
     */
    private function writeAgent(XMLWriter $xml, string $element, ?string $bic, string $bicTag): void
    {
        $xml->startElement($element);
        $xml->startElement('FinInstnId');

        if (filled($bic)) {
            $xml->writeElement($bicTag, SepaValidator::normalize($bic));
        } else {
            $xml->startElement('Othr');
            $xml->writeElement('Id', 'NOTPROVIDED');
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
    }

    /**
     * @param  list<array{amount: string|float}>  $transactions
     */
    private function sum(array $transactions): string
    {
        $cents = array_sum(array_map(fn (array $transaction) => (int) round((float) $transaction['amount'] * 100), $transactions));

        return number_format($cents / 100, 2, '.', '');
    }

    private function amount(string|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
