<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CashBookController;
use App\Http\Controllers\CircularController;
use App\Http\Controllers\DemoModeController;
use App\Http\Controllers\DutyPlanIcalController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberAuthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberPortalDocumentController;
use App\Http\Controllers\MembershipFeePrescriptionController;
use App\Livewire\CashBook\Index as CashBookIndex;
use App\Livewire\CashBook\Years as CashBookYears;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\DutyPlan\Absences as DutyPlanAbsences;
use App\Livewire\DutyPlan\Index as DutyPlanIndex;
use App\Livewire\DutyPlan\Volunteers as DutyPlanVolunteers;
use App\Livewire\ExternalContacts\Index;
use App\Livewire\Invoices\Articles\Index as InvoiceArticlesIndex;
use App\Livewire\Invoices\Create as InvoicesCreate;
use App\Livewire\Invoices\Index as InvoicesIndex;
use App\Livewire\Invoices\Recipients\Index as InvoiceRecipientsIndex;
use App\Livewire\Invoices\Show as InvoicesShow;
use App\Livewire\MemberAuth\Login;
use App\Livewire\MemberPortal\MyCirculars;
use App\Livewire\MemberPortal\MyDuties as MemberPortalMyDuties;
use App\Livewire\MemberPortal\MyFees;
use App\Livewire\MemberPortal\Profile;
use App\Livewire\MemberPortal\SelectProfile;
use App\Livewire\Members\Birthdays as MembersBirthdays;
use App\Livewire\Members\Create as MembersCreate;
use App\Livewire\Members\Edit as MembersEdit;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Members\Show as MembersShow;
use App\Livewire\MembershipFees\Index as MembershipFeesIndex;
use App\Livewire\Templates\Index as TemplatesIndex;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])
        ->name('password.request');

    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->name('password.update');

    Route::livewire('/member/login', Login::class)
        ->name('member.login');

    Route::get('/member/login/{token}', [MemberAuthController::class, 'magicLogin'])
        ->name('member.magic-login');
});

Route::middleware(['auth', 'active.staff'])->group(function () {

    Route::get('/', fn () => redirect()->route('dashboard'));

    Route::post('/demo/enter', [DemoModeController::class, 'enter'])
        ->name('demo.enter');

    Route::post('/demo/leave', [DemoModeController::class, 'leave'])
        ->name('demo.leave');

    Route::livewire('/dashboard', DashboardIndex::class)
        ->name('dashboard');

    Route::livewire('/members', MembersIndex::class)
        ->name('members.index');

    Route::livewire('/members/create', MembersCreate::class)
        ->name('members.create');

    Route::livewire('/members/birthdays', MembersBirthdays::class)
        ->name('members.birthdays');

    Route::get('/members/birthdays/pdf', [MemberController::class, 'downloadBirthdaysPdf'])
        ->name('members.birthdays.pdf');

    Route::livewire('/members/{member}', MembersShow::class)
        ->name('members.show');

    Route::livewire('/members/{member}/edit', MembersEdit::class)
        ->name('members.edit');

    Route::livewire('/duty-plan', DutyPlanIndex::class)
        ->name('duty-plan.index');

    Route::livewire('/duty-plan/volunteers', DutyPlanVolunteers::class)
        ->name('duty-plan.volunteers');

    Route::livewire('/duty-plan/absences', DutyPlanAbsences::class)
        ->name('duty-plan.absences');

    Route::livewire('/membership-fees', MembershipFeesIndex::class)
        ->name('membership-fees.index');

    Route::livewire('/templates', TemplatesIndex::class)
        ->name('templates.index');

    Route::get('/membership-fees/{entry}/prescription', [MembershipFeePrescriptionController::class, 'download'])
        ->name('membership-fees.prescription');

    Route::get('/templates/{template}/preview', [MembershipFeePrescriptionController::class, 'preview'])
        ->name('templates.preview');

    Route::get('/membership-fees/{year}/prescriptions', [MembershipFeePrescriptionController::class, 'downloadAll'])
        ->name('membership-fees.prescriptions.all');

    Route::get('/membership-fees/prescriptions/selected', [MembershipFeePrescriptionController::class, 'downloadSelected'])
        ->name('membership-fees.prescriptions.selected');

    Route::get('/membership-fee-prescriptions/{prescription}/pdf', [MembershipFeePrescriptionController::class, 'showStoredPdf'])
        ->name('membership-fee-prescriptions.pdf');

    Route::get('/membership-fees/reminders/selected', [MembershipFeePrescriptionController::class, 'downloadSelectedReminders'])
        ->name('membership-fees.reminders.selected');

    Route::get('/membership-fees/{year}/open-overview', [MembershipFeePrescriptionController::class, 'downloadOpenOverview'])
        ->name('membership-fees.open-overview');

    Route::get('/members/pdf/overview', [MemberController::class, 'downloadOverview'])
        ->name('members.pdf.overview');

    Route::livewire('/external-contacts', Index::class)
        ->name('external-contacts.index');

    Route::livewire('/circulars', App\Livewire\Circulars\Index::class)
        ->name('circulars.index');

    Route::get('/circulars/{circular}/recipient/{member}/preview', [CircularController::class, 'previewRecipient'])
        ->name('circulars.recipient.preview');

    Route::get('/circulars/{circular}/post-pdf', [CircularController::class, 'downloadPostBatch'])
        ->name('circulars.post-pdf');

    Route::post('/circulars/{circular}/recipients/{recipient}/test-mail', [CircularController::class, 'sendTestMail'])
        ->name('circulars.test-mail');

    Route::post('/circulars/{circular}/send-emails', [CircularController::class, 'sendAllEmails'])
        ->name('circulars.send-emails');

    Route::post('/circulars/{circular}/mark-post-sent', [CircularController::class, 'markPostAsSent'])
        ->name('circulars.mark-post-sent');

    Route::get('/circulars/{circular}/recipients/{recipient}/email', [CircularController::class, 'showEmail'])
        ->name('circulars.email-preview');

    Route::get('/circular-attachments/{attachment}', [CircularController::class, 'showAttachment'])
        ->name('circular-attachments.show');

    Route::livewire('/recipient-groups', App\Livewire\RecipientGroups\Index::class)
        ->name('recipient-groups.index');

    Route::livewire('/skills', App\Livewire\Skills\Index::class)
        ->name('skills.index');

    Route::livewire('/users', App\Livewire\Admin\Users\Index::class)
        ->middleware('admin')
        ->name('admin.users.index');

    Route::livewire('/settings', App\Livewire\Admin\Settings\Index::class)
        ->middleware('admin')
        ->name('admin.settings.index');

    Route::livewire('/activity-log', App\Livewire\Admin\ActivityLog\Index::class)
        ->middleware('admin')
        ->name('admin.activity-log.index');

    Route::livewire('/cash-book', CashBookIndex::class)
        ->middleware('invoices')
        ->name('cash-book.index');

    Route::livewire('/cash-book/years', CashBookYears::class)
        ->middleware('invoices')
        ->name('cash-book.years');

    Route::get('/cash-book/years/{year}/report', [CashBookController::class, 'report'])
        ->middleware('invoices')
        ->name('cash-book.report');

    Route::get('/cash-book/attachments/{attachment}', [CashBookController::class, 'showAttachment'])
        ->middleware('invoices')
        ->name('cash-book.attachments.show');

    Route::livewire('/invoices', InvoicesIndex::class)
        ->middleware('invoices')
        ->name('invoices.index');

    Route::livewire('/invoices/create', InvoicesCreate::class)
        ->middleware('invoices')
        ->name('invoices.create');

    Route::livewire('/invoices/{invoice}/edit', InvoicesCreate::class)
        ->middleware('invoices')
        ->name('invoices.edit');

    Route::livewire('/invoices/{invoice}', InvoicesShow::class)
        ->middleware('invoices')
        ->name('invoices.show');

    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
        ->middleware('invoices')
        ->name('invoices.pdf');

    Route::livewire('/invoice-recipients', InvoiceRecipientsIndex::class)
        ->middleware('invoices')
        ->name('invoice-recipients.index');

    Route::livewire('/invoice-articles', InvoiceArticlesIndex::class)
        ->middleware('invoices')
        ->name('invoice-articles.index');

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});

Route::middleware('auth:member')->group(function () {
    Route::livewire('/member/profile', Profile::class)
        ->name('member.profile');

    Route::livewire('/member/select-profile', SelectProfile::class)
        ->name('member.select-profile');

    Route::post('/member/logout', [MemberAuthController::class, 'logout'])
        ->name('member.logout');

    Route::livewire('/member/duties', MemberPortalMyDuties::class)
        ->name('member.duties');

    Route::livewire('/member/fees', MyFees::class)
        ->name('member.fees');

    Route::get('/member/fees/documents/{prescription}', [MemberPortalDocumentController::class, 'feeDocument'])
        ->name('member.fees.document');

    Route::livewire('/member/circulars', MyCirculars::class)
        ->name('member.circulars');

    Route::get('/member/circulars/{circular}/pdf', [MemberPortalDocumentController::class, 'circularPdf'])
        ->name('member.circulars.pdf');

    Route::get('/member/circulars/attachments/{attachment}', [MemberPortalDocumentController::class, 'circularAttachment'])
        ->name('member.circulars.attachment');
});

Route::get('/calendar/duty/{token}.ics', [DutyPlanIcalController::class, 'show'])
    ->name('duty-plan.ical');
