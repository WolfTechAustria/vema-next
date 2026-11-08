<?php

namespace App\Livewire\Inventory;

use App\Enums\InventoryCondition;
use App\Exports\InventoryExport;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\InventoryPhotoService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Materialliste mit Suche und Filtern.
 */
class Index extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $conditionFilter = '';

    /**
     * all | available | issued | inspection_due | overdue_return
     */
    #[Url]
    public string $statusFilter = 'all';

    public function export(): BinaryFileResponse
    {
        $items = $this->filteredQuery()->with(['category', 'currentLoan.member'])->get();

        return Excel::download(new InventoryExport($items), 'material-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * @return Builder<InventoryItem>
     */
    private function filteredQuery(): Builder
    {
        $search = trim($this->search);

        return InventoryItem::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('inventory_number', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhereHas('currentLoan.member', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('surname', 'like', $like)));
                });
            })
            ->when($this->categoryFilter !== '', fn (Builder $query) => $query->where('categoryID', (int) $this->categoryFilter))
            ->when(
                $this->conditionFilter !== '',
                fn (Builder $query) => $query->where('condition', $this->conditionFilter),
                fn (Builder $query) => $query->inService()
            )
            ->when($this->statusFilter === 'available', fn (Builder $query) => $query->whereDoesntHave('currentLoan'))
            ->when($this->statusFilter === 'issued', fn (Builder $query) => $query->whereHas('currentLoan'))
            ->when($this->statusFilter === 'inspection_due', fn (Builder $query) => $query->inspectionDue())
            ->when($this->statusFilter === 'overdue_return', fn (Builder $query) => $query->whereHas('currentLoan', fn (Builder $query) => $query->whereDate('due_on', '<', today())))
            ->orderByRaw('inventory_number IS NULL')
            ->orderBy('inventory_number')
            ->orderBy('name');
    }

    public function render()
    {
        return view('livewire.inventory.index', [
            'items' => $this->filteredQuery()->with(['category', 'currentLoan.member'])->get(),
            'categories' => InventoryCategory::query()->orderBy('name')->get(),
            'conditions' => InventoryCondition::cases(),
            'photos' => app(InventoryPhotoService::class),
        ])->layout('layouts.app', [
            'title' => 'Material | VEMA',
            'heading' => 'Material',
        ]);
    }
}
