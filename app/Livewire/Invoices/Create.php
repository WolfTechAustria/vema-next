<?php

namespace App\Livewire\Invoices;

use App\Models\Article;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceRecipient;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\InvoiceCalculator;
use App\Services\InvoiceFinalizer;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Create extends Component
{
    public ?int $invoiceID = null;

    public ?int $recipientID = null;
    public string $recipientSearch = '';

    public bool $showNewRecipientForm = false;
    public string $newRecipientCompanyName = '';
    public string $newRecipientName = '';
    public string $newRecipientSurname = '';
    public string $newRecipientStreet = '';
    public string $newRecipientZip = '';
    public string $newRecipientCity = '';
    public string $newRecipientEmail = '';
    public string $newRecipientPhone = '';
    public string $newRecipientVatId = '';

    public string $purpose = '';
    public string $invoice_date;
    public ?string $due_date = null;
    public string $intro_text = '';
    public string $footer_text = '';
    public bool $small_business_no_vat = false;

    public array $items = [];

    public function mount(?Invoice $invoice = null): void
    {
        $settings = Setting::current();

        $this->invoice_date = now()->format('Y-m-d');
        $this->small_business_no_vat = (bool) $settings->small_business_default;
        $this->footer_text = $settings->invoice_footer_text ?? '';

        if ($invoice) {
            abort_unless($invoice->isDraft(), 422, 'Diese Rechnung wurde bereits finalisiert und kann nicht mehr bearbeitet werden.');

            $invoice->load('items');

            $this->invoiceID = $invoice->invoiceID;
            $this->recipientID = $invoice->recipientID;
            $this->purpose = $invoice->purpose ?? '';
            $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
            $this->due_date = $invoice->due_date?->format('Y-m-d');
            $this->intro_text = $invoice->intro_text ?? '';
            $this->footer_text = $invoice->footer_text ?? '';
            $this->small_business_no_vat = (bool) $invoice->small_business_no_vat;

            $this->items = $invoice->items->map(fn (InvoiceItem $item) => [
                'articleID' => $item->articleID,
                'description' => $item->description,
                'quantity' => number_format((float) $item->quantity, 2, ',', ''),
                'unit' => $item->unit ?? 'Stk',
                'price_net' => number_format((float) $item->price_net, 2, ',', ''),
                'discount_percent' => number_format((float) $item->discount_percent, 2, ',', ''),
                'tax_rate' => number_format((float) $item->tax_rate, 2, ',', ''),
            ])->all();
        } else {
            $this->items = [
                $this->emptyItem($settings->invoice_default_tax_rate ?? 20),
            ];
        }
    }

    private function emptyItem(float $taxRate): array
    {
        return [
            'articleID' => null,
            'description' => '',
            'quantity' => '1',
            'unit' => 'Stk',
            'price_net' => '',
            'discount_percent' => '0',
            'tax_rate' => number_format($taxRate, 2, ',', ''),
        ];
    }

    public function addItem(): void
    {
        $this->items[] = $this->emptyItem((float) (Setting::current()->invoice_default_tax_rate ?? 20));
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Wird beim Verlassen des Beschreibungsfelds ausgelöst: passt die Zeile
     * an, falls der eingegebene Text exakt einem bestehenden Artikel
     * entspricht (Preis/USt./Einheit werden übernommen).
     */
    public function fillFromArticle(int $index): void
    {
        if (!isset($this->items[$index])) {
            return;
        }

        $article = Article::findByName($this->items[$index]['description'] ?? '');

        if (!$article) {
            $this->items[$index]['articleID'] = null;

            return;
        }

        $this->items[$index]['articleID'] = $article->articleID;
        $this->items[$index]['unit'] = $article->unit ?? 'Stk';
        $this->items[$index]['price_net'] = number_format((float) $article->price_net, 2, ',', '');
        $this->items[$index]['tax_rate'] = $this->defaultTaxRateOverride() ?? number_format((float) $article->tax_rate, 2, ',', '');
    }

    /**
     * Ist die globale USt.-Vorgabe (Vereinseinstellungen) auf 0 gesetzt, gilt
     * das als "keine USt." für den ganzen Verein — dann hat 0% immer Vorrang
     * vor einem abweichenden, ggf. älteren Artikel-Satz.
     */
    private function defaultTaxRateOverride(): ?string
    {
        $defaultRate = (float) (Setting::current()->invoice_default_tax_rate ?? 0);

        return $defaultRate === 0.0 ? '0,00' : null;
    }

    public function selectRecipient(int $recipientID): void
    {
        $this->recipientID = $recipientID;
        $this->recipientSearch = '';
        $this->showNewRecipientForm = false;
    }

    public function changeRecipient(): void
    {
        $this->recipientID = null;
    }

    public function showRecipientCreateForm(): void
    {
        $this->showNewRecipientForm = true;
        $this->resetValidation();
    }

    public function cancelNewRecipient(): void
    {
        $this->reset([
            'newRecipientCompanyName',
            'newRecipientName',
            'newRecipientSurname',
            'newRecipientStreet',
            'newRecipientZip',
            'newRecipientCity',
            'newRecipientEmail',
            'newRecipientPhone',
            'newRecipientVatId',
            'showNewRecipientForm',
        ]);

        $this->resetValidation();
    }

    public function saveNewRecipient(): void
    {
        $validated = $this->validate([
            'newRecipientCompanyName' => ['nullable', 'string', 'max:200'],
            'newRecipientName' => ['nullable', 'string', 'max:150'],
            'newRecipientSurname' => ['nullable', 'string', 'max:150'],
            'newRecipientStreet' => ['nullable', 'string', 'max:150'],
            'newRecipientZip' => ['nullable', 'string', 'max:20'],
            'newRecipientCity' => ['nullable', 'string', 'max:100'],
            'newRecipientEmail' => ['nullable', 'email', 'max:150'],
            'newRecipientPhone' => ['nullable', 'string', 'max:50'],
            'newRecipientVatId' => ['nullable', 'string', 'max:50'],
        ]);

        if (trim($validated['newRecipientCompanyName']) === '' && trim($validated['newRecipientSurname']) === '') {
            $this->addError('newRecipientCompanyName', 'Bitte entweder einen Firmennamen oder Vor-/Nachnamen angeben.');

            return;
        }

        $recipient = InvoiceRecipient::create([
            'company_name' => $validated['newRecipientCompanyName'] ?: null,
            'name' => $validated['newRecipientName'] ?: null,
            'surname' => $validated['newRecipientSurname'] ?: null,
            'street' => $validated['newRecipientStreet'] ?: null,
            'zip' => $validated['newRecipientZip'] ?: null,
            'city' => $validated['newRecipientCity'] ?: null,
            'email' => $validated['newRecipientEmail'] ?: null,
            'phone' => $validated['newRecipientPhone'] ?: null,
            'vat_id' => $validated['newRecipientVatId'] ?: null,
            'active' => true,
        ]);

        $this->cancelNewRecipient();
        $this->selectRecipient($recipient->recipientID);

        session()->flash('success', 'Empfänger wurde angelegt und übernommen.');
    }

    protected function rules(): array
    {
        return [
            'recipientID' => ['required', 'integer', 'exists:tb_invoice_recipients,recipientID'],
            'purpose' => ['nullable', 'string', 'max:200'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'intro_text' => ['nullable', 'string'],
            'footer_text' => ['nullable', 'string'],
            'small_business_no_vat' => ['boolean'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.price_net' => ['required'],
            'items.*.discount_percent' => ['nullable'],
            'items.*.tax_rate' => ['required'],
        ];
    }

    protected function messages(): array
    {
        return [
            'recipientID.required' => 'Bitte einen Rechnungsempfänger auswählen.',
        ];
    }

    private function normalizeDecimal(mixed $value): ?float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * Validiert und normalisiert die Positionszeilen zu reinen Zahlenwerten.
     * Gibt null zurück (und setzt Validierungsfehler), wenn eine Zeile
     * ungültige Zahlen enthält.
     */
    private function normalizedItems(): ?array
    {
        $normalized = [];
        $valid = true;

        foreach ($this->items as $index => $item) {
            $quantity = $this->normalizeDecimal($item['quantity'] ?? null);
            $priceNet = $this->normalizeDecimal($item['price_net'] ?? null);
            $discountPercent = $this->normalizeDecimal($item['discount_percent'] ?? '0') ?? 0.0;
            $taxRate = $this->normalizeDecimal($item['tax_rate'] ?? null);

            if ($quantity === null) {
                $this->addError('items.' . $index . '.quantity', 'Bitte eine gültige Menge eingeben.');
                $valid = false;
            }

            if ($priceNet === null) {
                $this->addError('items.' . $index . '.price_net', 'Bitte einen gültigen Preis eingeben.');
                $valid = false;
            }

            if ($taxRate === null) {
                $this->addError('items.' . $index . '.tax_rate', 'Bitte einen gültigen USt.-Satz eingeben.');
                $valid = false;
            }

            $normalized[] = [
                'articleID' => $item['articleID'] ?? null,
                'description' => trim((string) $item['description']),
                'quantity' => $quantity ?? 0.0,
                'unit' => $item['unit'] ?: 'Stk',
                'price_net' => $priceNet ?? 0.0,
                'discount_percent' => $discountPercent,
                'tax_rate' => $taxRate ?? 0.0,
            ];
        }

        return $valid ? $normalized : null;
    }

    private function persist(): ?Invoice
    {
        $this->validate();

        $lines = $this->normalizedItems();

        if ($lines === null) {
            return null;
        }

        return DB::transaction(function () use ($lines) {
            $invoice = $this->invoiceID
                ? Invoice::findOrFail($this->invoiceID)
                : new Invoice(['created_by' => auth()->id()]);

            abort_unless($invoice->exists === false || $invoice->isDraft(), 422, 'Diese Rechnung wurde bereits finalisiert.');

            $totals = InvoiceCalculator::invoiceTotals($lines, $this->small_business_no_vat);

            $invoice->fill([
                'recipientID' => $this->recipientID,
                'purpose' => $this->purpose ?: null,
                'invoice_date' => $this->invoice_date,
                'due_date' => $this->due_date ?: null,
                'intro_text' => $this->intro_text ?: null,
                'footer_text' => $this->footer_text ?: null,
                'small_business_no_vat' => $this->small_business_no_vat,
                'status' => $invoice->status ?? 'draft',
                'subtotal_net' => $totals['subtotal_net'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
                'total_gross' => $totals['total_gross'],
            ]);

            $invoice->save();

            $invoice->items()->delete();

            foreach ($lines as $sortOrder => $line) {
                $article = Article::findByName($line['description']);

                if (!$article) {
                    $article = Article::create([
                        'name' => $line['description'],
                        'unit' => $line['unit'],
                        'price_net' => $line['price_net'],
                        'tax_rate' => $line['tax_rate'],
                        'active' => true,
                    ]);
                }

                $lineTotals = InvoiceCalculator::lineTotals(
                    $line['quantity'],
                    $line['price_net'],
                    $line['discount_percent'],
                    $line['tax_rate'],
                    $this->small_business_no_vat
                );

                InvoiceItem::create([
                    'invoiceID' => $invoice->invoiceID,
                    'articleID' => $article->articleID,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'price_net' => $line['price_net'],
                    'discount_percent' => $line['discount_percent'],
                    'tax_rate' => $line['tax_rate'],
                    'line_total_net' => $lineTotals['net'],
                    'line_total_gross' => $lineTotals['gross'],
                    'sort_order' => $sortOrder,
                ]);
            }

            return $invoice;
        });
    }

    public function saveDraft()
    {
        $invoice = $this->persist();

        if (!$invoice) {
            return;
        }

        ActivityLogger::log(
            $this->invoiceID ? 'invoice.draft_updated' : 'invoice.draft_created',
            'Rechnungsentwurf für "' . $invoice->recipient->display_name . '" wurde gespeichert.',
            'Invoice',
            $invoice->invoiceID
        );

        session()->flash('success', 'Rechnung wurde als Entwurf gespeichert.');

        return $this->redirectRoute('invoices.show', ['invoice' => $invoice->invoiceID], navigate: true);
    }

    public function saveAndFinalize()
    {
        $invoice = $this->persist();

        if (!$invoice) {
            return;
        }

        $invoice = app(InvoiceFinalizer::class)->finalize($invoice);

        ActivityLogger::log(
            'invoice.finalized',
            'Rechnung ' . $invoice->invoice_number . ' wurde erstellt.',
            'Invoice',
            $invoice->invoiceID
        );

        session()->flash('success', 'Rechnung ' . $invoice->invoice_number . ' wurde erstellt.');

        return $this->redirectRoute('invoices.show', ['invoice' => $invoice->invoiceID], navigate: true);
    }

    public function render()
    {
        $selectedRecipient = $this->recipientID
            ? InvoiceRecipient::find($this->recipientID)
            : null;

        $availableRecipients = collect();

        if (!$selectedRecipient && trim($this->recipientSearch) !== '') {
            $search = '%' . trim($this->recipientSearch) . '%';

            $availableRecipients = InvoiceRecipient::query()
                ->where('active', true)
                ->where(function ($query) use ($search) {
                    $query
                        ->where('company_name', 'like', $search)
                        ->orWhere('name', 'like', $search)
                        ->orWhere('surname', 'like', $search);
                })
                ->orderBy('company_name')
                ->orderBy('surname')
                ->limit(10)
                ->get();
        }

        $lines = $this->normalizedItemsForPreview();

        $previewTotals = InvoiceCalculator::invoiceTotals($lines, $this->small_business_no_vat);

        $itemLineTotals = [];

        foreach ($lines as $index => $line) {
            $itemLineTotals[$index] = InvoiceCalculator::lineTotals(
                $line['quantity'],
                $line['price_net'],
                $line['discount_percent'],
                $line['tax_rate'],
                $this->small_business_no_vat
            );
        }

        $articles = Article::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return view('livewire.invoices.create', [
            'selectedRecipient' => $selectedRecipient,
            'availableRecipients' => $availableRecipients,
            'previewTotals' => $previewTotals,
            'itemLineTotals' => $itemLineTotals,
            'articles' => $articles,
        ])->layout('layouts.app', [
            'title' => ($this->invoiceID ? 'Rechnung bearbeiten' : 'Rechnung erstellen') . ' | VEMA',
            'heading' => $this->invoiceID ? 'Rechnung bearbeiten' : 'Rechnung erstellen',
        ]);
    }

    /**
     * Wie normalizedItems(), aber ohne Validierungsfehler zu setzen — nur für
     * die Live-Vorschau der Summen während der Eingabe.
     */
    private function normalizedItemsForPreview(): array
    {
        $lines = [];

        foreach ($this->items as $item) {
            $lines[] = [
                'quantity' => $this->normalizeDecimal($item['quantity'] ?? null) ?? 0.0,
                'price_net' => $this->normalizeDecimal($item['price_net'] ?? null) ?? 0.0,
                'discount_percent' => $this->normalizeDecimal($item['discount_percent'] ?? null) ?? 0.0,
                'tax_rate' => $this->normalizeDecimal($item['tax_rate'] ?? null) ?? 0.0,
            ];
        }

        return $lines;
    }
}
