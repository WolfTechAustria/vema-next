<?php

namespace App\Livewire\MemberPortal;

use App\Models\MemberFile;
use App\Services\ActiveMemberResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Dokumente, die der Verein für das Mitglied freigegeben hat
 * (z. B. Beitrittserklärung, Vereinbarungen).
 */
class MyDocuments extends Component
{
    public function render(): View
    {
        $member = app(ActiveMemberResolver::class)->resolve();

        $documents = MemberFile::query()
            ->where('memberID', $member->memberID)
            ->visibleToMember()
            ->orderByDesc('created_at')
            ->orderByDesc('fileID')
            ->get();

        return view('livewire.member-portal.my-documents', [
            'documents' => $documents,
        ])->layout('layouts.app', [
            'title' => 'Meine Dokumente | VEMA',
            'heading' => 'Meine Dokumente',
        ]);
    }
}
