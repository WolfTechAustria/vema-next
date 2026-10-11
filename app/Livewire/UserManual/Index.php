<?php

namespace App\Livewire\UserManual;

use App\Services\UserManual;
use Livewire\Component;

class Index extends Component
{
    public function render(UserManual $manual)
    {
        return view('livewire.user-manual.index', [
            'manual' => $manual->contents(),
        ])->layout('layouts.app', [
            'title' => 'Anleitung | VEMA',
            'heading' => 'Anleitung',
        ]);
    }
}
