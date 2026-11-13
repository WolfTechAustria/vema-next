<?php

namespace App\Services;

use App\Models\DutyCalendarShare;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Mitglieder geben ihre Dienste anderen Mitgliedern frei, die sie dann im
 * eigenen Kalender-Abo oder über einen eigenen Link sehen.
 */
class DutyCalendarShares
{
    public const MIN_SEARCH_LENGTH = 3;

    public const MAX_SEARCH_RESULTS = 10;

    /**
     * Aktive Mitglieder, deren Name den Suchbegriff enthält.
     *
     * @return Collection<int, Member>
     */
    public function searchByName(Member $owner, string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_SEARCH_LENGTH) {
            return collect();
        }

        $query = $this->candidates($owner);

        // Jedes Wort muss im Vor- oder Nachnamen vorkommen ("Anna Hub").
        foreach (preg_split('/\s+/', $term) as $word) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $word).'%';

            $query->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('surname', 'like', $like));
        }

        return $query
            ->orderBy('surname')
            ->orderBy('name')
            ->limit(self::MAX_SEARCH_RESULTS)
            ->get(['memberID', 'name', 'surname']);
    }

    /**
     * Aktive Mitglieder mit genau dieser E-Mail-Adresse (Familien teilen
     * sich oft eine Adresse).
     *
     * @return Collection<int, Member>
     */
    public function searchByEmail(Member $owner, string $email): Collection
    {
        $email = mb_strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return collect();
        }

        return $this->candidates($owner)
            ->whereHas('emails', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))
            ->orderBy('surname')
            ->orderBy('name')
            ->limit(self::MAX_SEARCH_RESULTS)
            ->get(['memberID', 'name', 'surname']);
    }

    public function share(Member $owner, Member $viewer): DutyCalendarShare
    {
        if ($owner->memberID === $viewer->memberID) {
            throw ValidationException::withMessages(['shareSearch' => 'Du kannst deine Dienste nicht dir selbst freigeben.']);
        }

        if (! $viewer->active) {
            throw ValidationException::withMessages(['shareSearch' => 'Dienste können nur aktiven Mitgliedern freigegeben werden.']);
        }

        $share = DutyCalendarShare::query()->firstOrCreate([
            'ownerID' => $owner->memberID,
            'viewerID' => $viewer->memberID,
        ]);

        if ($share->wasRecentlyCreated) {
            ActivityLogger::log(
                'portal.duty_share_created',
                'Mitglied "'.$owner->full_name.'" (#'.$owner->memberID.') hat seine Dienste für "'.$viewer->full_name.'" (#'.$viewer->memberID.') freigegeben.',
                'Member',
                $owner->memberID
            );
        }

        return $share;
    }

    /**
     * Freigabe beenden – durch das freigebende oder das empfangende Mitglied.
     */
    public function end(DutyCalendarShare $share, Member $by): void
    {
        $share->loadMissing(['owner', 'viewer']);
        $share->delete();

        ActivityLogger::log(
            'portal.duty_share_ended',
            'Mitglied "'.$by->full_name.'" (#'.$by->memberID.') hat die Freigabe der Dienste von "'
                .$share->owner?->full_name.'" für "'.$share->viewer?->full_name.'" beendet.',
            'Member',
            $by->memberID
        );
    }

    private function candidates(Member $owner)
    {
        return Member::query()
            ->where('active', true)
            ->where('memberID', '!=', $owner->memberID);
    }
}
