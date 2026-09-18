<?php

namespace App\Services;

use App\Models\Member;
use App\Models\RecipientGroup;
use Illuminate\Support\Collection;

class BirthdayRecipientService
{
    /**
     * @return Collection<int, string>
     */
    public function resolveEmails(): Collection
    {
        $group = RecipientGroup::query()
            ->where('name', config('birthday.recipient_group'))
            ->first();

        if (!$group) {
            return collect();
        }

        $memberEmails = $group->members()
            ->get()
            ->flatMap(
                fn (Member $member) => $member->emails->pluck('email')
            );

        $externalEmails = $group->externalContacts()
            ->get()
            ->pluck('email')
            ->filter();

        return $memberEmails
            ->merge($externalEmails)
            ->filter()
            ->unique()
            ->values();
    }
}
