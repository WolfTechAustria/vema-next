<?php

namespace App\Services;

use App\Models\MembershipFeeEntry;
use App\Models\Template;
use App\Models\ExternalContact;
use App\Models\Member;

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

    public function circular(
        string $bodyHtml,
        Member|ExternalContact $recipient
    ): string
    {
        if ($recipient instanceof Member) {

            $recipient->loadMissing('city');

            $salutation = match (strtolower((string) $recipient->gender)) {
                'm', 'male', 'mann', 'männlich' => 'Lieber',
                default => 'Liebe',
            };

            $replacements = [
                '{{first_name}}' => $recipient->name ?? '',
                '{{last_name}}' => $recipient->surname ?? '',
                '{{full_name}}' => $recipient->full_name ?? '',
                '{{street}}' => $recipient->street ?? '',
                '{{zip}}' => $recipient->zip ?? '',
                '{{city}}' => $recipient->city?->city ?? '',
                '{{salutation}}' => $salutation,
            ];

        } else {

            /*
             * Externer Kontakt
             *
             * Hier haben wir aktuell kein Geschlecht und keine Adresse.
             * Deshalb verwenden wir eine neutrale Anrede.
             */
            $replacements = [
                '{{first_name}}' => $recipient->name ?? '',
                '{{last_name}}' => $recipient->surname ?? '',
                '{{full_name}}' => $recipient->full_name ?? '',
                '{{street}}' => '',
                '{{zip}}' => '',
                '{{city}}' => '',
                '{{salutation}}' => 'Guten Tag',
            ];
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $bodyHtml
        );
    }
}
