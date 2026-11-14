<?php

use App\Services\Sepa\SepaValidator;

it('accepts valid IBANs regardless of spaces and case', function (string $iban) {
    expect((new SepaValidator)->isValidIban($iban))->toBeTrue();
})->with([
    'Österreich' => 'AT61 1904 3002 3457 3201',
    'Deutschland' => 'de89370400440532013000',
]);

it('rejects IBANs with a wrong check digit, length or country', function (string $iban) {
    expect((new SepaValidator)->isValidIban($iban))->toBeFalse();
})->with([
    'Prüfziffer' => 'AT611904300234573202',
    'zu kurz' => 'AT61190430023457320',
    'unbekanntes Land' => 'XX611904300234573201',
    'leer' => '',
]);

it('validates creditor identifiers and BICs', function () {
    $validator = new SepaValidator;

    expect($validator->isValidCreditorId('DE98ZZZ09999999999'))->toBeTrue()
        ->and($validator->isValidCreditorId('AT92 ZZZ 00000012345'))->toBeTrue()
        ->and($validator->isValidCreditorId('AT93ZZZ00000012345'))->toBeFalse()
        ->and($validator->isValidBic('BKAUATWW'))->toBeTrue()
        ->and($validator->isValidBic('RLNWATWWXXX'))->toBeTrue()
        ->and($validator->isValidBic('BKAU12'))->toBeFalse();
});
