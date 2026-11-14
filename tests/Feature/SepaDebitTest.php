<?php

use App\Enums\CashBookEntryType;
use App\Enums\Feature;
use App\Enums\Plan;
use App\Livewire\Admin\Settings\Sepa as SepaSettings;
use App\Livewire\MemberPortal\MyFees;
use App\Livewire\Members\SepaMandateCard;
use App\Livewire\MembershipFees\Index as MembershipFeesIndex;
use App\Livewire\MembershipFees\SepaDebit;
use App\Mail\SepaMandateGrantedMail;
use App\Mail\SepaPreNotificationMail;
use App\Models\CashBookEntry;
use App\Models\CashBookYear;
use App\Models\Member;
use App\Models\MemberEmail;
use App\Models\MembershipFeeEntry;
use App\Models\MembershipFeeYear;
use App\Models\SepaDebitRun;
use App\Models\SepaMandate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sepa\Pain008Writer;
use App\Services\SepaDebitRuns;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');

    Setting::current()->update([
        'name' => 'Musikverein Testdorf',
        'iban' => 'AT611904300234573201',
        'bic' => 'BKAUATWW',
        'sepa_creditor_id' => 'AT92ZZZ00000012345',
    ]);

    $this->feeYear = MembershipFeeYear::create(['year' => 2026, 'name' => '2026', 'default_amount' => 40, 'active' => true, 'due_date' => today()->toDateString()]);
});

function treasurer(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('kassier', 'web'));
    test()->actingAs($user, 'web');

    return $user;
}

/**
 * Mitglied mit offenem Beitrag und (optional) Lastschriftmandat.
 */
function feeMember(MembershipFeeYear $year, string $surname, float $amount = 40, bool $withMandate = true): MembershipFeeEntry
{
    $member = Member::create(['gender' => 'w', 'name' => 'Anna', 'surname' => $surname, 'active' => 1]);
    MemberEmail::create(['memberID' => $member->memberID, 'email' => strtolower($surname).'@example.test']);

    if ($withMandate) {
        SepaMandate::factory()->create(['memberID' => $member->memberID, 'account_holder' => 'Anna '.$surname]);
    }

    return MembershipFeeEntry::create(['yearID' => $year->yearID, 'memberID' => $member->memberID, 'amount' => $amount, 'status' => 'open']);
}

function collectionDay(): string
{
    return app(SepaDebitRuns::class)->earliestCollectionDate()->addWeek()->toDateString();
}

describe('debit run', function () {
    it('creates a pain.008 file for open fees with a mandate only', function () {
        treasurer();
        $huber = feeMember($this->feeYear, 'Huber', 40);
        $mueller = feeMember($this->feeYear, 'Müller', 25.5);
        $without = feeMember($this->feeYear, 'Ohnemandat', withMandate: false);

        Livewire::test(SepaDebit::class, ['year' => $this->feeYear])
            ->assertSee('Ohnemandat')
            ->assertSet('selected', [(string) $huber->entryID, (string) $mueller->entryID])
            ->set('collectionDate', collectionDay())
            ->call('createRun')
            ->assertHasNoErrors();

        $run = SepaDebitRun::query()->sole();
        expect($run->entry_count)->toBe(2)
            ->and((float) $run->total_amount)->toBe(65.5)
            ->and($huber->fresh()->status)->toBe(MembershipFeeEntry::STATUS_PENDING)
            ->and($without->fresh()->status)->toBe('open');

        Mail::assertSent(SepaPreNotificationMail::class, 2);

        $xml = Storage::disk('local')->get($run->file_path);
        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('p', 'urn:iso:std:iso:20022:tech:xsd:'.Pain008Writer::VERSION_08);

        expect($xpath->evaluate('string(//p:GrpHdr/p:NbOfTxs)'))->toBe('2')
            ->and($xpath->evaluate('string(//p:GrpHdr/p:CtrlSum)'))->toBe('65.50')
            ->and($xpath->evaluate('string(//p:PmtInf/p:PmtTpInf/p:SeqTp)'))->toBe('FRST')
            ->and($xpath->evaluate('string(//p:CdtrSchmeId//p:Othr/p:Id)'))->toBe('AT92ZZZ00000012345')
            ->and($xml)->toContain('Anna Mueller')
            ->and($xml)->toContain('<IBAN>AT611904300234573201</IBAN>');

        $this->get(route('membership-fees.sepa.download', $run))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    });

    it('refuses to create a run without creditor id', function () {
        Setting::current()->update(['sepa_creditor_id' => null]);
        treasurer();
        feeMember($this->feeYear, 'Huber');

        Livewire::test(SepaDebit::class, ['year' => $this->feeYear])
            ->assertSee('Gläubiger-ID')
            ->call('createRun')
            ->assertHasErrors('sepa');

        expect(SepaDebitRun::query()->exists())->toBeFalse();
    });

    it('locks fees in collection against manual status changes', function () {
        treasurer();
        $entry = feeMember($this->feeYear, 'Huber');
        app(SepaDebitRuns::class)->create($this->feeYear, [$entry->entryID], now()->parse(collectionDay()), null);

        Livewire::test(MembershipFeesIndex::class)
            ->set('yearId', $this->feeYear->yearID)
            ->call('updateStatus', $entry->entryID, 'paid');

        expect($entry->fresh()->status)->toBe(MembershipFeeEntry::STATUS_PENDING);
    });
});

describe('confirmation', function () {
    it('marks fees paid only after confirmation and books one summary entry', function () {
        treasurer();
        $cashYear = CashBookYear::factory()->create(['start_date' => today()->startOfYear()->toDateString(), 'end_date' => today()->addYear()->endOfYear()->toDateString()]);
        $huber = feeMember($this->feeYear, 'Huber', 40);
        $bauer = feeMember($this->feeYear, 'Bauer', 30);
        $run = app(SepaDebitRuns::class)->create($this->feeYear, [$huber->entryID, $bauer->entryID], now()->parse(collectionDay()), null);

        Livewire::test(SepaDebit::class, ['year' => $this->feeYear])->call('confirmRun', $run->runID);

        expect($huber->fresh()->status)->toBe('paid')
            ->and($huber->fresh()->payment_method)->toBe('sepa')
            ->and($huber->fresh()->paid_at->toDateString())->toBe(collectionDay())
            ->and($run->fresh()->status)->toBe(SepaDebitRun::STATUS_CONFIRMED)
            ->and(SepaMandate::query()->whereNull('first_used_at')->exists())->toBeFalse();

        $booking = CashBookEntry::query()->sole();
        expect($booking->type)->toBe(CashBookEntryType::Income)
            ->and((float) $booking->amount)->toBe(70.0)
            ->and($booking->category)->toBe('Mitgliedsbeiträge')
            ->and($booking->cashBookYearID)->toBe($cashYear->cashBookYearID);
    });

    it('books one entry per member and reverses a returned debit', function () {
        Setting::current()->update(['sepa_cash_book_mode' => SepaDebitRuns::CASH_BOOK_PER_MEMBER]);
        treasurer();
        CashBookYear::factory()->create(['start_date' => today()->startOfYear()->toDateString(), 'end_date' => today()->addYear()->endOfYear()->toDateString()]);
        $huber = feeMember($this->feeYear, 'Huber', 40);
        $bauer = feeMember($this->feeYear, 'Bauer', 30);
        $runs = app(SepaDebitRuns::class);
        $run = $runs->create($this->feeYear, [$huber->entryID, $bauer->entryID], now()->parse(collectionDay()), null);
        $runs->confirm($run, null);

        expect(CashBookEntry::query()->where('type', CashBookEntryType::Income)->count())->toBe(2);

        $item = $run->items()->where('entryID', $huber->entryID)->sole();
        Livewire::test(SepaDebit::class, ['year' => $this->feeYear])->call('markReturned', $item->itemID);

        expect($huber->fresh()->status)->toBe('open')
            ->and($item->fresh()->returned_at)->not->toBeNull()
            ->and((float) CashBookEntry::query()->where('type', CashBookEntryType::Expense)->sole()->amount)->toBe(40.0);
    });

    it('uses recurring sequence type for mandates already used', function () {
        treasurer();
        $entry = feeMember($this->feeYear, 'Huber');
        $entry->member->activeSepaMandate->update(['first_used_at' => now()->subYear()]);

        $run = app(SepaDebitRuns::class)->create($this->feeYear, [$entry->entryID], now()->parse(collectionDay()), null);

        expect($run->items()->sole()->sequence_type)->toBe('RCUR')
            ->and(Storage::disk('local')->get($run->file_path))->toContain('<SeqTp>RCUR</SeqTp>');
    });

    it('sets fees back to open when a run is discarded', function () {
        treasurer();
        Setting::current()->update(['sepa_cash_book_mode' => SepaDebitRuns::CASH_BOOK_NONE]);
        $entry = feeMember($this->feeYear, 'Huber');
        $run = app(SepaDebitRuns::class)->create($this->feeYear, [$entry->entryID], now()->parse(collectionDay()), null);

        Livewire::test(SepaDebit::class, ['year' => $this->feeYear])->call('cancelRun', $run->runID);

        expect($entry->fresh()->status)->toBe('open')
            ->and($run->fresh()->status)->toBe(SepaDebitRun::STATUS_CANCELLED)
            ->and(CashBookEntry::query()->exists())->toBeFalse();
    });
});

describe('mandates', function () {
    it('lets treasurers record a mandate and hides bank data from other staff', function () {
        treasurer();
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);

        Livewire::test(SepaMandateCard::class, ['member' => $member])
            ->call('startEditing')
            ->set('iban', 'AT61 1904 3002 3457 3202')
            ->call('save')
            ->assertHasErrors('iban')
            ->set('iban', 'AT61 1904 3002 3457 3201')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('AT61 1904 3002 3457 3201');

        $mandate = SepaMandate::query()->sole();
        expect($mandate->iban)->toBe('AT611904300234573201')
            ->and($mandate->getRawOriginal('iban'))->not->toContain('AT6119');

        $this->actingAs(User::factory()->create(), 'web');

        Livewire::test(SepaMandateCard::class, ['member' => $member])
            ->assertDontSee('3201')
            ->assertSee('Mandat vorhanden')
            ->call('startEditing')
            ->assertForbidden();
    });

    it('lets members grant and revoke a mandate online', function () {
        $member = loginPortalMember('Muster');
        $admin = User::factory()->admin()->create(['email' => 'kassa@example.test']);

        Livewire::test(MyFees::class)
            ->call('openMandateForm')
            ->assertSee('AT92ZZZ00000012345')
            ->set('iban', 'AT611904300234573201')
            ->call('grantMandate')
            ->assertHasErrors('consent')
            ->set('consent', true)
            ->call('grantMandate')
            ->assertHasNoErrors()
            ->assertSee('AT61 **** **** **** 3201');

        $mandate = SepaMandate::query()->sole();
        expect($mandate->source)->toBe(SepaMandate::SOURCE_PORTAL)
            ->and($mandate->consent_text)->toContain('Musikverein Testdorf')
            ->and($mandate->signed_on->isToday())->toBeTrue();
        Mail::assertSent(SepaMandateGrantedMail::class, fn ($mail) => $mail->hasTo($admin->email));

        Livewire::test(MyFees::class)->call('revokeMandate');

        expect($mandate->fresh()->revoked_at)->not->toBeNull()
            ->and($mandate->fresh()->revoked_by_member)->toBeTrue()
            ->and($member->activeSepaMandate()->exists())->toBeFalse();
    });

    it('replaces the previous mandate with a new one', function () {
        treasurer();
        $member = Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1]);
        $old = SepaMandate::factory()->create(['memberID' => $member->memberID]);

        Livewire::test(SepaMandateCard::class, ['member' => $member])
            ->call('startEditing')
            ->set('iban', 'DE89370400440532013000')
            ->call('save');

        expect($old->fresh()->revoked_at)->not->toBeNull()
            ->and($member->activeSepaMandate()->first()->iban)->toBe('DE89370400440532013000');
    });
});

describe('access', function () {
    it('is only for treasurers and clubs with the feature', function () {
        $this->actingAs(User::factory()->create(), 'web');
        $this->get(route('membership-fees.sepa', $this->feeYear))->assertForbidden();

        treasurer();
        $this->get(route('membership-fees.sepa', $this->feeYear))->assertOk();

        expect(Plan::Basis->defaultFeatures())->not->toContain(Feature::SepaDirectDebit)
            ->and(Plan::Verein->defaultFeatures())->toContain(Feature::SepaDirectDebit);
    });

    it('lets admins save the SEPA settings with a valid creditor id', function () {
        $this->actingAs(User::factory()->admin()->create(), 'web');

        Livewire::test(SepaSettings::class)
            ->set('creditorId', 'AT93ZZZ00000012345')
            ->call('save')
            ->assertHasErrors('creditorId')
            ->set('creditorId', 'de98 zzz 09999999999')
            ->set('cashBookMode', SepaDebitRuns::CASH_BOOK_NONE)
            ->call('save')
            ->assertHasNoErrors();

        expect(Setting::current()->sepa_creditor_id)->toBe('DE98ZZZ09999999999')
            ->and(Setting::current()->sepa_cash_book_mode)->toBe('none');
    });
});
