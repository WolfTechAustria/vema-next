<?php

namespace App\Livewire\Central\Admin;

use App\Enums\Plan;
use App\Enums\TenantStatus;
use App\Mail\Central\PlatformNewsMail;
use App\Models\PlatformNews;
use App\Models\Tenant;
use App\Services\PlatformNewsDrafts;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

/**
 * Neuigkeiten zu VEMA verfassen und veröffentlichen. Sie erscheinen bei den
 * Admins der Vereine unter „Neuigkeiten“, auf Wunsch auch per E-Mail an die
 * Kontaktadresse jedes Vereins.
 */
class News extends Component
{
    public bool $showForm = false;

    public ?int $editingNewsId = null;

    public string $title = '';

    public string $body = '';

    /** Paket-Wert oder '' = alle Vereine */
    public string $minPlan = '';

    public bool $sendEmail = false;

    /** Beim Öffnen aus einem Update übernommene Entwürfe */
    public int $importedDrafts = 0;

    /**
     * Mit einem Update ausgelieferte Neuigkeiten als Entwürfe übernehmen.
     */
    public function mount(PlatformNewsDrafts $drafts): void
    {
        try {
            $this->importedDrafts = $drafts->sync();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
            'minPlan' => ['nullable', Rule::enum(Plan::class)],
            'sendEmail' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => 'Bitte einen Titel eingeben.',
            'body.required' => 'Bitte einen Text eingeben.',
        ];
    }

    public function newNews(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editNews(int $newsId): void
    {
        $news = PlatformNews::findOrFail($newsId);

        $this->editingNewsId = $news->id;
        $this->title = $news->title;
        $this->body = $news->body;
        $this->minPlan = $news->min_plan?->value ?? '';
        $this->sendEmail = false;
        $this->showForm = true;

        $this->resetValidation();
    }

    public function saveDraft(): void
    {
        $news = $this->store();
        $this->resetForm();

        session()->flash('success', '„'.$news->title.'“ gespeichert'.($news->isPublished() ? '.' : ' (Entwurf).'));
    }

    public function publish(): void
    {
        $news = $this->store();
        $alreadyPublished = $news->isPublished();

        if (! $alreadyPublished) {
            $news->update(['published_at' => now()]);
        }

        $message = '„'.$news->title.'“ '.($alreadyPublished ? 'gespeichert.' : 'veröffentlicht.');

        if ($this->sendEmail && $news->emailed_at === null) {
            $sent = $this->mailToClubs($news);
            $message .= ' E-Mail an '.$sent.' '.($sent === 1 ? 'Verein' : 'Vereine').' gesendet.';
        }

        $this->resetForm();

        session()->flash('success', $message);
    }

    public function unpublish(int $newsId): void
    {
        $news = PlatformNews::findOrFail($newsId);
        $news->update(['published_at' => null]);

        session()->flash('success', '„'.$news->title.'“ ist wieder ein Entwurf.');
    }

    public function deleteNews(int $newsId): void
    {
        $news = PlatformNews::findOrFail($newsId);
        $news->delete();

        if ($this->editingNewsId === $newsId) {
            $this->resetForm();
        }

        session()->flash('success', '„'.$news->title.'“ gelöscht.');
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    private function store(): PlatformNews
    {
        $validated = $this->validate();

        $attributes = [
            'title' => trim($validated['title']),
            'body' => trim($validated['body']),
            'min_plan' => $validated['minPlan'] !== '' ? $validated['minPlan'] : null,
        ];

        if ($this->editingNewsId) {
            $news = PlatformNews::findOrFail($this->editingNewsId);
            $news->update($attributes);

            return $news;
        }

        return PlatformNews::create($attributes + ['platform_admin_id' => auth('platform')->id()]);
    }

    /**
     * E-Mail an die Kontaktadresse jedes erreichbaren Vereins, für den die
     * Neuigkeit gedacht ist (Paket).
     */
    private function mailToClubs(PlatformNews $news): int
    {
        $clubs = Tenant::query()
            ->where('status', TenantStatus::Active)
            ->whereNotNull('provisioned_at')
            ->whereNotNull('contact_email')
            ->get()
            ->filter(fn (Tenant $tenant) => $tenant->isAccessible() && $news->isVisibleTo($tenant));

        $sent = 0;

        foreach ($clubs as $club) {
            try {
                Mail::to($club->contact_email)->send(new PlatformNewsMail($news, $club));
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $news->update(['emailed_at' => now(), 'emailed_count' => $sent]);

        Log::info('[Plattform-Admin] Neuigkeit per E-Mail versendet', [
            'admin' => auth('platform')->user()?->email,
            'news' => $news->title,
            'clubs' => $sent,
        ]);

        return $sent;
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingNewsId', 'title', 'body', 'minPlan', 'sendEmail']);
    }

    public function render()
    {
        return view('livewire.central.admin.news', [
            'newsItems' => PlatformNews::query()->with('admin')->orderByRaw('published_at IS NULL DESC')->latest('published_at')->latest('id')->get(),
            'plans' => Plan::cases(),
            'editingNews' => $this->editingNewsId ? PlatformNews::find($this->editingNewsId) : null,
        ])->layout('layouts.platform-admin', [
            'title' => 'Neuigkeiten | Plattform-Admin',
        ]);
    }
}
