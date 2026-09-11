<?php

namespace App\Livewire\Templates;

use App\Models\Template;
use Livewire\Component;

class Index extends Component
{

    public ?int $selectedTemplateID = null;
    public $templates;

    public string $name = '';
    public string $subject = '';
    public string $bodyHtml = '';

    // In der Livewire-Komponente
    public array $placeholders = [
        '{{first_name}}' => 'Vorname',
        '{{last_name}}' => 'Nachname',
        '{{full_name}}' => 'Vollständiger Name',
        '{{street}}' => 'Straße',
        '{{zip}}' => 'PLZ',
        '{{city}}' => 'Ort',
        '{{year}}' => 'Jahr',
        '{{amount}}' => 'Betrag',
        '{{due_date}}' => 'Fälligkeit',
        '{{salutation}}' => 'Anrede',
        '{{reminder_level}}' => 'Erinnerungsstufe',
    ];

    public function mount(): void
    {
        $this->templates = \App\Models\Template::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $template = $this->templates->first();

        if ($template) {
            $this->selectedTemplateID = $template->templateID;

            $this->loadTemplate();
        }
    }

    public function updatedSelectedTemplateID(): void
    {
        $this->loadTemplate();
    }

    protected function loadTemplate(): void
    {
        if (!$this->selectedTemplateID) {
            return;
        }

        $template = \App\Models\Template::findOrFail(
            $this->selectedTemplateID
        );

        $this->name = $template->name;
        $this->subject = $template->subject ?? '';
        $this->bodyHtml = $template->body_html ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'subject' => [
                'nullable',
                'string',
                'max:255',
            ],
            'bodyHtml' => [
                'required',
                'string',
            ],
        ]);

        $template = Template::findOrFail(
            $this->selectedTemplateID
        );

        $template->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body_html' => $validated['bodyHtml'],
            'active' => true,
        ]);

        $this->selectedTemplateID =
            $template->templateID;

        session()->flash(
            'success',
            'Template wurde gespeichert.'
        );
    }

    public function render()
    {
        return view('livewire.templates.index')
            ->layout('layouts.app', [
                'title' => 'Templates | VEMA',
                'heading' => 'Templates',
            ]);
    }
}
