<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MembershipFeePrescriptionController;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Members\Show as MembersShow;
use App\Livewire\Members\Edit as MembersEdit;
use App\Livewire\Members\Create as MembersCreate;
use App\Livewire\DutyPlan\Index as DutyPlanIndex;
use App\Livewire\DutyPlan\Absences as DutyPlanAbsences;
use App\Livewire\DutyPlan\Volunteers as DutyPlanVolunteers;
use App\Livewire\MembershipFees\Index as MembershipFeesIndex;
use App\Http\Controllers\CircularController;

use App\Livewire\Templates\Index as TemplatesIndex;




use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {

    Route::get('/', fn () => redirect()->route('dashboard'));

    Route::livewire('/dashboard', DashboardIndex::class)
        ->name('dashboard');

    Route::livewire('/members', MembersIndex::class)
        ->name('members.index');

    Route::livewire('/members/create', MembersCreate::class)
        ->name('members.create');

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

    Route::get('/membership-fees/{entry}/prescription',[MembershipFeePrescriptionController::class, 'download'])
        ->name('membership-fees.prescription');

    Route::get('/templates/{template}/preview', [MembershipFeePrescriptionController::class, 'preview'])
        ->name('templates.preview');

    Route::get('/membership-fees/{year}/prescriptions',[MembershipFeePrescriptionController::class, 'downloadAll'])
        ->name('membership-fees.prescriptions.all');

    Route::get('/membership-fees/prescriptions/selected',[MembershipFeePrescriptionController::class, 'downloadSelected'])
        ->name('membership-fees.prescriptions.selected');

    Route::get('/membership-fee-prescriptions/{prescription}/pdf',[MembershipFeePrescriptionController::class, 'showStoredPdf'])
        ->name('membership-fee-prescriptions.pdf');

    Route::get('/membership-fees/reminders/selected',[MembershipFeePrescriptionController::class, 'downloadSelectedReminders'])
        ->name('membership-fees.reminders.selected');

    Route::get('/membership-fees/{year}/open-overview',[MembershipFeePrescriptionController::class, 'downloadOpenOverview'])
        ->name('membership-fees.open-overview');

    Route::get('/members/pdf/overview',[\App\Http\Controllers\MemberController::class, 'downloadOverview'])
        ->name('members.pdf.overview');

    Route::livewire('/circulars',\App\Livewire\Circulars\Index::class)
        ->name('circulars.index');

    Route::get('/circulars/{circular}/recipient/{member}/preview', [CircularController::class, 'previewRecipient'])
        ->name('circulars.recipient.preview');

    Route::get('/circulars/{circular}/post-pdf', [CircularController::class, 'downloadPostBatch'])
        ->name('circulars.post-pdf');

    Route::post('/circulars/{circular}/recipients/{recipient}/test-mail', [CircularController::class, 'sendTestMail'])
        ->name('circulars.test-mail');

    Route::post('/circulars/{circular}/send-emails',[CircularController::class, 'sendAllEmails'])
        ->name('circulars.send-emails');

    Route::post('/circulars/{circular}/mark-post-sent', [CircularController::class, 'markPostAsSent'])
        ->name('circulars.mark-post-sent');

    Route::get('/circulars/{circular}/recipients/{recipient}/email', [CircularController::class, 'showEmail'])
        ->name('circulars.email-preview');

    Route::get('/circular-attachments/{attachment}', [CircularController::class, 'showAttachment'])
        ->name('circular-attachments.show');

    Route::livewire('/recipient-groups', \App\Livewire\RecipientGroups\Index::class)
        ->name('recipient-groups.index');


    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');
});
