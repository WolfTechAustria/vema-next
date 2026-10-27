<?php

use App\Livewire\Admin\Settings\Documents;
use App\Livewire\DutyPlan\Index as DutyPlanIndex;
use App\Models\DutyPlan;
use App\Models\DutyPlanAssignment;
use App\Models\DutyPlanEvent;
use App\Models\DutyPlanRole;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\BirthdayListPdf;
use App\Services\ClubBranding;
use App\Services\PdfLetterheadService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('branding');

    $this->admin = User::factory()->admin()->create();
});

function storeListBackground(): void
{
    app(ClubBranding::class)->store(
        'list_background',
        UploadedFile::fake()->createWithContent('hintergrund.pdf', Pdf::loadHTML('<p>Hintergrund</p>')->output())
    );
}

/**
 * @return array{plan: DutyPlan, events: Collection<int, DutyPlanEvent>}
 */
function dutyPlanWithHelpers(int $helperCount = 2): array
{
    $plan = DutyPlan::create([
        'name' => 'Saison 2026-2027',
        'date_from' => '2026-09-01',
        'date_to' => '2027-05-31',
        'pdf_note' => 'bei Verhinderung bitte selbstständig Ersatz suchen',
    ]);
    $role = DutyPlanRole::create(['planID' => $plan->planID, 'name' => 'Bar', 'weekday_mask' => 127]);
    $event = DutyPlanEvent::create(['planID' => $plan->planID, 'roleID' => $role->roleID, 'duty_date' => '2026-09-14', 'duty_name' => 'Bar', 'required_helpers' => $helperCount]);

    $helpers = [1 => ['Wolfgang', 'Oberhauser'], 2 => ['Sonja', 'Strillinger'], 3 => ['Hannes', 'Egger'], 4 => ['Renate', 'Casazza']];

    foreach (array_slice($helpers, 0, $helperCount, true) as $slot => [$name, $surname]) {
        $member = Member::create(['gender' => 'm', 'name' => $name, 'surname' => $surname, 'active' => 1]);
        DutyPlanAssignment::create(['eventID' => $event->eventID, 'slot_no' => $slot, 'memberID' => $member->memberID]);
    }

    return ['plan' => $plan, 'events' => DutyPlanEvent::with('assignments.member')->get()];
}

function renderDutyPlanView(array $dutyPlan): string
{
    return view('pdf.duty-plan', [
        'events' => $dutyPlan['events'],
        'title' => 'Arbeitseinteilung '.$dutyPlan['plan']->name,
        'note' => $dutyPlan['plan']->pdf_note,
        'dateFrom' => '2026-09-01',
        'dateTo' => '2027-05-31',
        'maxHelperColumns' => $dutyPlan['events']->max('required_helpers'),
    ])->render();
}

test('the duty plan PDF follows the template with weekday, date and helpers joined by &', function () {
    $html = renderDutyPlanView(dutyPlanWithHelpers());

    expect($html)->toContain('Arbeitseinteilung Saison 2026-2027')
        ->toContain('(bei Verhinderung bitte selbstständig Ersatz suchen)')
        ->toMatch('/<td class="nowrap">\s*MO\s*<\/td>/')
        ->toContain('14.09.2026')
        ->toContain('Wolfgang Oberhauser')
        ->toContain('&amp;')
        ->toContain('Sonja Strillinger');
});

test('from four helpers on the duty plan PDF lists all names in one wrapping cell', function () {
    $html = renderDutyPlanView(dutyPlanWithHelpers(4));

    expect($html)->toContain('Wolfgang Oberhauser, Sonja Strillinger, Hannes Egger &amp; Renate Casazza')
        ->toContain('list-table full-width')
        ->not->toContain('class="separator');
});

test('list PDFs use the configured page margins', function () {
    Setting::current()->update(['list_margin_top' => 40, 'list_margin_left' => 20]);

    expect(renderDutyPlanView(dutyPlanWithHelpers()))->toContain('margin: 40mm 15mm 15mm 20mm;');
});

test('list PDFs show the club logo only without a list background', function () {
    app(ClubBranding::class)->store('logo', UploadedFile::fake()->image('logo.png'));

    expect(renderDutyPlanView(dutyPlanWithHelpers()))->toContain('Vereinslogo');

    storeListBackground();

    expect(renderDutyPlanView(dutyPlanWithHelpers()))->not->toContain('Vereinslogo');
});

test('list PDFs stay unchanged without a list background and get it laid under once uploaded', function () {
    $content = Pdf::loadHTML('<p>Dienstplan</p>')->output();
    $service = app(PdfLetterheadService::class);

    expect($service->applyListBackground($content))->toBe($content);

    storeListBackground();

    expect($service->applyListBackground($content))->not->toBe($content);
});

test('the duty plan PDF export downloads a file', function () {
    $plan = dutyPlanWithHelpers()['plan'];

    Livewire::actingAs($this->admin)
        ->test(DutyPlanIndex::class)
        ->call('selectPlan', $plan->planID)
        ->call('exportPdf')
        ->assertFileDownloaded('Dienstplan_Alle_2026-09-01_2027-05-31.pdf');
});

test('the PDF note is saved with the duty plan', function () {
    $plan = DutyPlan::create(['name' => 'Saison 2026', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31']);

    Livewire::actingAs($this->admin)
        ->test(DutyPlanIndex::class)
        ->call('selectPlan', $plan->planID)
        ->set('pdfNote', 'Dienstbeginn: 17:30 Uhr')
        ->call('savePlan')
        ->assertHasNoErrors();

    expect($plan->fresh()->pdf_note)->toBe('Dienstbeginn: 17:30 Uhr');
});

test('the birthday list PDF uses the list layout and highlights round birthdays', function () {
    $member = Member::create(['gender' => 'w', 'name' => 'Erika', 'surname' => 'Beispiel', 'active' => 1, 'dateOfBirth' => now()->subYears(60)->startOfMonth()->toDateString()]);

    $html = view('pdf.members-birthdays', [
        'members' => app(BirthdayListPdf::class)->members(now()->month),
        'monthName' => now()->translatedFormat('F Y'),
        'year' => now()->year,
    ])->render();

    expect($html)->toContain('list-table full-width')
        ->toContain($member->full_name)
        ->toContain('runder Geburtstag')
        ->toContain('class="highlight"');

    expect(app(BirthdayListPdf::class)->render(now()->month))->toStartWith('%PDF');
});

test('list margins and the list background are saved in the document settings', function () {
    Livewire::actingAs($this->admin)
        ->test(Documents::class)
        ->set('listBackgroundUpload', UploadedFile::fake()->create('hintergrund.pdf', 100, 'application/pdf'))
        ->assertHasNoErrors()
        ->set('list_margin_top', 45)
        ->call('saveListLayout')
        ->assertHasNoErrors();

    Storage::disk('branding')->assertExists('list-background.pdf');

    expect(app(ClubBranding::class)->listMargins())->toBe(['top' => 45, 'right' => 15, 'bottom' => 15, 'left' => 15]);
});

test('the list background must be a PDF and margins stay within range', function () {
    Livewire::actingAs($this->admin)
        ->test(Documents::class)
        ->set('listBackgroundUpload', UploadedFile::fake()->image('hintergrund.png'))
        ->assertHasErrors('listBackgroundUpload')
        ->set('list_margin_bottom', 150)
        ->call('saveListLayout')
        ->assertHasErrors('list_margin_bottom');

    expect(Setting::current()->list_background_path)->toBeNull();
});
