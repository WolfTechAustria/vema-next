<?php

namespace App\Services;

use App\Models\MembershipFeeEntry;
use App\Models\Template;

class TemplateRendererService
{
    public function membershipFeePrescription(
        Template $template,
        MembershipFeeEntry $entry
    ): string {
        $entry->loadMissing([
            'member.city',
            'year',
        ]);

        $member = $entry->member;
        $year = $entry->year;

        $amount = $entry->amount
            ?? $year->default_amount;

        $gender = mb_strtolower(
            trim((string) $member->gender)
        );

        $salutation = in_array(
            $gender,
            ['herr', 'm', 'male', 'männlich'],
            true
        )
            ? 'lieber'
            : 'liebe';

        $variables = [
            '{{first_name}}' => $member->name ?? '',
            '{{last_name}}' => $member->surname ?? '',

            '{{full_name}}' => trim(
                ($member->name ?? '')
                . ' '
                . ($member->surname ?? '')
            ),

            '{{street}}' => $member->street ?? '',
            '{{zip}}' => $member->zip ?? '',
            '{{city}}' => $member->city?->city ?? '',

            '{{year}}' => (string) $year->year,

            '{{amount}}' => $amount !== null
                ? number_format(
                    (float) $amount,
                    2,
                    ',',
                    '.'
                )
                : '–',

            '{{due_date}}' => $year->due_date
                ? $year->due_date->format('d.m.Y')
                : '',

            '{{salutation}}' => $salutation,
        ];

        return str_replace(
            array_keys($variables),
            array_values($variables),
            $template->body_html
        );
    }

    public function membershipFeeReminder(
        \App\Models\Template $template,
        \App\Models\MembershipFeeEntry $entry,
        int $reminderLevel
    ): string {
        $member = $entry->member;
        $year = $entry->year;

        $amount = $entry->amount
            ?? $year->default_amount;

        $gender = mb_strtolower(
            trim((string) $member->gender)
        );

        $salutation = in_array(
            $gender,
            ['herr', 'm', 'male', 'männlich'],
            true
        )
            ? 'lieber'
            : 'liebe';

        $replacements = [
            '{{first_name}}' => $member->name,
            '{{last_name}}' => $member->surname,
            '{{full_name}}' => trim(
                $member->name . ' ' . $member->surname
            ),

            '{{street}}' => $member->street,
            '{{zip}}' => $member->zip,
            '{{city}}' => $member->city?->city ?? '',

            '{{year}}' => (string) $year->year,

            '{{amount}}' => number_format(
                (float) $amount,
                2,
                ',',
                '.'
            ),

            '{{due_date}}' => $year->due_date
                ? $year->due_date->format('d.m.Y')
                : '',

            '{{salutation}}' => $salutation,

            '{{reminder_level}}' => (string) $reminderLevel,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template->body_html
        );
    }
}
