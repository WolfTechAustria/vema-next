<?php

namespace App\Livewire\MemberPortal;

use App\Models\InventoryLoan;
use App\Services\ActiveMemberResolver;
use App\Services\InventoryPhotoService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Material, das der Verein dem Mitglied ausgegeben hat (nur ansehen).
 */
class MyInventory extends Component
{
    public function render(): View
    {
        $member = app(ActiveMemberResolver::class)->resolve();

        $loans = $member->inventoryLoans()->with('item.category')->get();

        return view('livewire.member-portal.my-inventory', [
            'openLoans' => $loans->filter(fn (InventoryLoan $loan) => $loan->isOpen()),
            'pastLoans' => $loans->reject(fn (InventoryLoan $loan) => $loan->isOpen()),
            'photos' => app(InventoryPhotoService::class),
        ])->layout('layouts.app', [
            'title' => 'Mein Material | VEMA',
            'heading' => 'Mein Material',
        ]);
    }
}
