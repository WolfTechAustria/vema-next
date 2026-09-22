<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $statusFilter = 'all';

    public string $search = '';

    public function render()
    {
        $invoices = Invoice::query()
            ->with('recipient')
            ->when(
                $this->statusFilter !== 'all',
                fn ($query) => $query->where('status', $this->statusFilter)
            )
            ->when(
                trim($this->search) !== '',
                function ($query) {
                    $search = '%' . trim($this->search) . '%';

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('invoice_number', 'like', $search)
                            ->orWhere('purpose', 'like', $search)
                            ->orWhereHas('recipient', function ($query) use ($search) {
                                $query
                                    ->where('company_name', 'like', $search)
                                    ->orWhere('name', 'like', $search)
                                    ->orWhere('surname', 'like', $search);
                            });
                    });
                }
            )
            ->orderByDesc('invoiceID')
            ->get();

        $openTotal = Invoice::query()
            ->where('status', 'sent')
            ->sum('total_gross');

        return view('livewire.invoices.index', [
            'invoices' => $invoices,
            'openTotal' => $openTotal,
        ])->layout('layouts.app', [
            'title' => 'Rechnungen | VEMA',
            'heading' => 'Rechnungen',
        ]);
    }
}
