<?php

namespace App\Livewire\Invoices\Articles;

use App\Models\Article;
use App\Models\Setting;
use Livewire\Component;

class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingArticleID = null;

    public string $name = '';
    public string $description = '';
    public string $unit = 'Stk';
    public string $price_net = '';
    public string $tax_rate = '20';
    public bool $active = true;

    public string $search = '';

    public function mount(): void
    {
        $this->tax_rate = number_format((float) (Setting::current()->invoice_default_tax_rate ?? 20), 2, ',', '');
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:30'],
            'price_net' => ['required'],
            'tax_rate' => ['required'],
            'active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $priceNet = str_replace(',', '.', (string) $validated['price_net']);
        $taxRate = str_replace(',', '.', (string) $validated['tax_rate']);

        if (!is_numeric($priceNet)) {
            $this->addError('price_net', 'Bitte einen gültigen Preis eingeben.');

            return;
        }

        if (!is_numeric($taxRate)) {
            $this->addError('tax_rate', 'Bitte einen gültigen USt.-Satz eingeben.');

            return;
        }

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'unit' => $validated['unit'] ?: 'Stk',
            'price_net' => round((float) $priceNet, 2),
            'tax_rate' => round((float) $taxRate, 2),
            'active' => $validated['active'],
        ];

        if ($this->editingArticleID) {
            Article::findOrFail($this->editingArticleID)->update($data);

            session()->flash('success', 'Artikel wurde aktualisiert.');
        } else {
            Article::create($data);

            session()->flash('success', 'Artikel wurde angelegt.');
        }

        $this->resetForm();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $articleID): void
    {
        $article = Article::findOrFail($articleID);

        $this->showForm = true;
        $this->editingArticleID = $article->articleID;
        $this->name = $article->name;
        $this->description = $article->description ?? '';
        $this->unit = $article->unit ?? 'Stk';
        $this->price_net = number_format((float) $article->price_net, 2, ',', '');
        $this->tax_rate = number_format((float) $article->tax_rate, 2, ',', '');
        $this->active = (bool) $article->active;

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function toggleActive(int $articleID): void
    {
        $article = Article::findOrFail($articleID);

        $article->update([
            'active' => !$article->active,
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm',
            'editingArticleID',
            'name',
            'description',
            'price_net',
        ]);

        $this->unit = 'Stk';
        $this->tax_rate = number_format((float) (Setting::current()->invoice_default_tax_rate ?? 20), 2, ',', '');
        $this->active = true;
    }

    public function render()
    {
        $articles = Article::query()
            ->when(
                trim($this->search) !== '',
                fn ($query) => $query->where('name', 'like', '%' . trim($this->search) . '%')
            )
            ->orderBy('name')
            ->get();

        return view('livewire.invoices.articles.index', [
            'articles' => $articles,
        ])->layout('layouts.app', [
            'title' => 'Artikel | VEMA',
            'heading' => 'Artikel',
        ]);
    }
}
