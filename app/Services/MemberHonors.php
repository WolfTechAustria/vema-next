<?php

namespace App\Services;

use App\Models\Honor;
use App\Models\Member;
use App\Models\MemberHonor;
use Illuminate\Support\Facades\DB;

/**
 * Ehrungen eintragen, ändern und entfernen. Die Bezeichnung wird frei
 * eingetippt; neue Bezeichnungen landen in der Vorschlagsliste, nicht mehr
 * verwendete verschwinden wieder daraus (z. B. Tippfehler).
 */
class MemberHonors
{
    public function add(Member $member, string $name, ?string $awardedOn, ?string $note): MemberHonor
    {
        return DB::transaction(function () use ($member, $name, $awardedOn, $note) {
            $memberHonor = MemberHonor::create([
                'memberID' => $member->memberID,
                'honorID' => $this->honorFor($name)->honorID,
                'awarded_on' => $awardedOn ?: null,
                'note' => filled($note) ? trim($note) : null,
            ]);

            ActivityLogger::log(
                'member.honor_added',
                'Mitglied "'.$member->full_name.'" (#'.$member->memberID.') erhielt die Ehrung "'.$memberHonor->honor->name.'".',
                'Member',
                $member->memberID,
                ActivityLogger::created(['honor' => $memberHonor->description()], ['honor' => 'Ehrung'])
            );

            return $memberHonor;
        });
    }

    public function update(MemberHonor $memberHonor, string $name, ?string $awardedOn, ?string $note): void
    {
        DB::transaction(function () use ($memberHonor, $name, $awardedOn, $note) {
            $before = $memberHonor->description();
            $previousHonor = $memberHonor->honor;

            $memberHonor->update([
                'honorID' => $this->honorFor($name)->honorID,
                'awarded_on' => $awardedOn ?: null,
                'note' => filled($note) ? trim($note) : null,
            ]);

            $memberHonor->load('honor');
            $this->forgetIfUnused($previousHonor);

            $changes = ActivityLogger::changes(['honor' => $before], ['honor' => $memberHonor->description()], ['honor' => 'Ehrung']);

            if ($changes !== []) {
                ActivityLogger::log(
                    'member.honor_updated',
                    'Ehrung von Mitglied "'.$memberHonor->member->full_name.'" (#'.$memberHonor->memberID.') wurde geändert.',
                    'Member',
                    $memberHonor->memberID,
                    $changes
                );
            }
        });
    }

    public function remove(MemberHonor $memberHonor): void
    {
        DB::transaction(function () use ($memberHonor) {
            $description = $memberHonor->description();
            $honor = $memberHonor->honor;

            $memberHonor->delete();
            $this->forgetIfUnused($honor);

            ActivityLogger::log(
                'member.honor_removed',
                'Ehrung "'.$honor->name.'" von Mitglied "'.$memberHonor->member->full_name.'" (#'.$memberHonor->memberID.') wurde entfernt.',
                'Member',
                $memberHonor->memberID,
                ActivityLogger::changes(['honor' => $description], [], ['honor' => 'Ehrung'])
            );
        });
    }

    /**
     * Vorhandene Bezeichnung (ohne Rücksicht auf Groß-/Kleinschreibung) oder neue.
     */
    private function honorFor(string $name): Honor
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return Honor::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
            ?? Honor::create(['name' => $name]);
    }

    private function forgetIfUnused(Honor $honor): void
    {
        if (! $honor->memberHonors()->exists()) {
            $honor->delete();
        }
    }
}
