<?php

use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Invoices\Index as InvoicesIndex;
use App\Livewire\Invoices\Show as InvoicesShow;
use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 22)->startOfDay());
    Storage::fake('local');
    Mail::fake();
});

/**
 * @param  array<int, string>  $roles
 */
function staffWithRoles(array $roles): User
{
    $user = User::factory()->create();

    foreach ($roles as $role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    return $user;
}

/**
 * Offene, finalisierte Rechnung mit Empfänger; Fälligkeit relativ zu heute.
 *
 * @param  array<string, mixed>  $overrides
 */
function openInvoice(array $overrides = [], ?string $email = 'sponsor@example.test'): Invoice
{
    $recipientID = DB::table('tb_invoice_recipients')->insertGetId([
        'company_name' => 'Sponsor GmbH',
        'email' => $email,
    ]);

    Storage::disk('local')->put('invoices/2026/Rechnung_2026-0001.pdf', '%PDF-1.4 test');

    return Invoice::query()->create([
        'recipientID' => $recipientID,
        'invoice_number' => '2026-0001',
        'invoice_date' => now()->subDays(40)->toDateString(),
        'due_date' => now()->subDays(20)->toDateString(),
        'total_gross' => 120,
        'status' => 'sent',
        'file_path' => 'invoices/2026/Rechnung_2026-0001.pdf',
        'file_name' => 'Rechnung_2026-0001.pdf',
        ...$overrides,
    ]);
}

function addReminder(Invoice $invoice, int $level, string $sentAt): void
{
    InvoiceReminder::query()->create([
        'invoiceID' => $invoice->invoiceID,
        'level' => $level,
        'delivery_method' => 'email',
        'file_path' => 'invoices/2026/reminders/test.pdf',
        'file_name' => 'test.pdf',
        'sent_to' => 'sponsor@example.test',
        'sent_at' => $sentAt,
    ]);
}

describe('due logic', function () {
    it('becomes due once the first-reminder period after the due date has passed', function () {
        $invoice = openInvoice(['due_date' => now()->subDays(14)->toDateString()]);

        expect($invoice->nextReminderLevel())->toBe(1)
            ->and($invoice->isReminderDue())->toBeTrue();

        $notYetDue = openInvoice(['due_date' => now()->subDays(13)->toDateString(), 'invoice_number' => '2026-0002']);

        expect($notYetDue->isReminderDue())->toBeFalse()
            ->and($notYetDue->nextReminderDate()->toDateString())->toBe(now()->addDay()->toDateString());
    });

    it('waits the interval after the last reminder before the next level', function () {
        $invoice = openInvoice();
        addReminder($invoice, 1, now()->subDays(13)->toDateTimeString());

        expect($invoice->fresh()->nextReminderLevel())->toBe(2)
            ->and($invoice->fresh()->isReminderDue())->toBeFalse();

        $this->travelTo(now()->addDay());

        expect($invoice->fresh()->isReminderDue())->toBeTrue();
    });

    it('stops after the third level', function () {
        $invoice = openInvoice();
        addReminder($invoice, 1, now()->subDays(60)->toDateTimeString());
        addReminder($invoice, 2, now()->subDays(40)->toDateTimeString());
        addReminder($invoice, 3, now()->subDays(20)->toDateTimeString());

        $invoice = $invoice->fresh();

        expect($invoice->nextReminderLevel())->toBeNull()
            ->and($invoice->isReminderDue())->toBeFalse();
    });

    it('never reminds drafts, paid or canceled invoices or invoices without due date', function (array $overrides) {
        expect(openInvoice($overrides)->isReminderDue())->toBeFalse();
    })->with([
        'draft' => [['status' => 'draft']],
        'paid' => [['status' => 'paid']],
        'canceled' => [['status' => 'canceled']],
        'no due date' => [['due_date' => null]],
    ]);

    it('lists only open invoices past their due date as overdue', function () {
        $overdue = openInvoice();
        openInvoice(['due_date' => now()->addDays(5)->toDateString(), 'invoice_number' => '2026-0002']);
        openInvoice(['status' => 'paid', 'invoice_number' => '2026-0003']);

        expect(Invoice::query()->overdue()->pluck('invoiceID')->all())->toBe([$overdue->invoiceID]);

        Livewire::actingAs(staffWithRoles(['kassier']))
            ->test(InvoicesIndex::class)
            ->set('statusFilter', 'overdue')
            ->assertSee('2026-0001')
            ->assertDontSee('2026-0002')
            ->assertDontSee('2026-0003');
    });
});

describe('sending', function () {
    it('sends the first reminder by email with reminder and invoice attached', function () {
        $invoice = openInvoice();

        Livewire::actingAs(staffWithRoles(['kassier']))
            ->test(InvoicesShow::class, ['invoice' => $invoice])
            ->call('sendReminderEmail')
            ->assertSee('Zahlungserinnerung wurde per E-Mail versendet.');

        $reminder = $invoice->reminders()->first();

        expect($reminder->level)->toBe(1)
            ->and($reminder->delivery_method)->toBe('email')
            ->and($reminder->sent_to)->toBe('sponsor@example.test');

        Storage::disk('local')->assertExists($reminder->file_path);

        Mail::assertSent(InvoiceReminderMail::class, function (InvoiceReminderMail $mail) {
            return $mail->hasTo('sponsor@example.test')
                && count($mail->attachments()) === 2;
        });

        $this->assertDatabaseHas('tb_activity_log', ['action' => 'invoice.reminder_sent']);
    });

    it('escalates through the levels and stops after the last one', function () {
        $invoice = openInvoice();

        $component = Livewire::actingAs(staffWithRoles(['kassier']))
            ->test(InvoicesShow::class, ['invoice' => $invoice]);

        foreach ([1, 2, 3] as $level) {
            $component->call('sendReminderEmail');
        }

        expect($invoice->reminders()->pluck('level')->all())->toBe([1, 2, 3]);

        $component->call('sendReminderEmail')
            ->assertSee('Es wurden bereits alle Mahnstufen versendet.');

        expect($invoice->reminders()->count())->toBe(3);
    });

    it('records a printed reminder without email recipient', function () {
        $invoice = openInvoice();

        Livewire::actingAs(staffWithRoles(['kassier']))
            ->test(InvoicesShow::class, ['invoice' => $invoice])
            ->call('createReminderPdf')
            ->assertRedirect(route('invoice-reminders.pdf', $invoice->reminders()->first()));

        $reminder = $invoice->reminders()->first();

        expect($reminder->delivery_method)->toBe('post')
            ->and($reminder->sent_to)->toBeNull();

        Mail::assertNothingSent();
        $this->assertDatabaseHas('tb_activity_log', ['action' => 'invoice.reminder_printed']);
    });

    it('refuses an email reminder without recipient address and leaves no trace', function () {
        $invoice = openInvoice(email: null);

        Livewire::actingAs(staffWithRoles(['kassier']))
            ->test(InvoicesShow::class, ['invoice' => $invoice])
            ->call('sendReminderEmail')
            ->assertSee('Für diesen Empfänger ist keine E-Mail-Adresse hinterlegt.');

        expect($invoice->reminders()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles('invoices/2026/reminders'))->toBeEmpty();

        Mail::assertNothingSent();
    });

    it('does not remind paid invoices', function () {
        $invoice = openInvoice(['status' => 'paid']);

        Livewire::actingAs(staffWithRoles(['kassier']))
            ->test(InvoicesShow::class, ['invoice' => $invoice])
            ->call('sendReminderEmail')
            ->assertSee('Nur offene Rechnungen können angemahnt werden.');

        expect($invoice->reminders()->count())->toBe(0);
    });
});

describe('access and settings', function () {
    it('serves reminder pdfs only to kassier and admin', function () {
        $invoice = openInvoice();
        Livewire::actingAs(staffWithRoles(['kassier']))
            ->test(InvoicesShow::class, ['invoice' => $invoice])
            ->call('createReminderPdf');
        $reminder = $invoice->reminders()->first();

        $this->actingAs(staffWithRoles(['kassier']))
            ->get(route('invoice-reminders.pdf', $reminder))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs(staffWithRoles([]))
            ->get(route('invoice-reminders.pdf', $reminder))
            ->assertForbidden();
    });

    it('saves the reminder periods in the club settings', function () {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(SettingsIndex::class)
            ->set('invoice_reminder_first_after_days', '7')
            ->set('invoice_reminder_interval_days', '10')
            ->call('save')
            ->assertHasNoErrors();

        expect(Setting::current()->invoice_reminder_first_after_days)->toBe(7)
            ->and(Setting::current()->invoice_reminder_interval_days)->toBe(10);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(SettingsIndex::class)
            ->set('invoice_reminder_interval_days', '0')
            ->call('save')
            ->assertHasErrors('invoice_reminder_interval_days');
    });
});
