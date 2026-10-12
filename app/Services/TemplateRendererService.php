<?php

namespace App\Services;

use App\Models\ExternalContact;
use App\Models\Member;
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
                .' '
                .($member->surname ?? '')
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
        Template $template,
        MembershipFeeEntry $entry,
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
                $member->name.' '.$member->surname
            ),

            '{{street}}' => $member->street ?? '',
            '{{zip}}' => $member->zip ?? '',
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
    ): string {
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

    /**
     * Rundschreiben für mehrere Empfänger mit derselben E-Mail-Adresse:
     * eine Nachricht, in der jede Person angesprochen wird — aus
     * „{{salutation}} {{first_name}}“ wird „Liebe Anna, lieber Max und
     * liebe Lisa“. Einzelne Platzhalter werden zu Aufzählungen.
     *
     * @param  iterable<Member|ExternalContact>  $recipients
     */
    public function circularForRecipients(string $bodyHtml, iterable $recipients): string
    {
        $recipients = collect($recipients)->values();

        if ($recipients->count() === 1) {
            return $this->circular($bodyHtml, $recipients->first());
        }

        $greetings = $recipients->map(function (Member|ExternalContact $recipient, int $index) {
            $salutation = $this->circularSalutation($recipient);

            return trim(($index === 0 ? $salutation : mb_strtolower(mb_substr($salutation, 0, 1)).mb_substr($salutation, 1))
                .' '.($recipient->name ?? ''));
        });

        // Die übliche Anrede „{{salutation}} {{first_name}}“ als Ganzes ersetzen.
        $bodyHtml = preg_replace(
            '/\{\{salutation\}\}(?:\s|&nbsp;)+\{\{first_name\}\}/u',
            $this->naturalList($greetings->all()),
            $bodyHtml
        );

        $firstMember = $recipients->first(fn ($recipient) => $recipient instanceof Member);
        $firstMember?->loadMissing('city');

        return str_replace(
            ['{{first_name}}', '{{last_name}}', '{{full_name}}', '{{street}}', '{{zip}}', '{{city}}', '{{salutation}}'],
            [
                $this->naturalList($recipients->pluck('name')->filter()->all()),
                $this->naturalList($recipients->pluck('surname')->filter()->unique()->all()),
                $this->naturalList($recipients->map(fn ($recipient) => $recipient->full_name)->filter()->all()),
                $firstMember?->street ?? '',
                $firstMember?->zip ?? '',
                $firstMember?->city?->city ?? '',
                $this->circularSalutation($recipients->first()),
            ],
            $bodyHtml
        );
    }

    private function circularSalutation(Member|ExternalContact $recipient): string
    {
        if (! $recipient instanceof Member) {
            return 'Guten Tag';
        }

        return match (strtolower((string) $recipient->gender)) {
            'm', 'male', 'mann', 'männlich' => 'Lieber',
            default => 'Liebe',
        };
    }

    /**
     * „Anna“, „Anna und Max“, „Anna, Max und Lisa“.
     *
     * @param  list<string>|array<int, string>  $items
     */
    private function naturalList(array $items): string
    {
        $items = array_values($items);

        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        return implode(', ', array_slice($items, 0, -1)).' und '.end($items);
    }
}
