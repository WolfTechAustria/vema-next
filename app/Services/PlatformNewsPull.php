<?php

namespace App\Services;

use App\Mail\VemaNewsMail;
use App\Models\User;
use App\Models\VemaNews;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Eigene Installation (TENANCY_MODE=single): übernimmt die auf der Plattform
 * veröffentlichten Neuigkeiten und mailt sie an die Admins — wenn sie auf der
 * Plattform per E-Mail verschickt wurden. Beim allerersten Abruf werden die
 * vorhandenen Neuigkeiten nur übernommen, nicht gemailt.
 */
class PlatformNewsPull
{
    public function isConfigured(): bool
    {
        return filled(config('tenancy.news_feed_url')) && filled(config('tenancy.news_feed_token'));
    }

    /**
     * @return array{created: int, updated: int, deleted: int, emailed: int}
     */
    public function pull(): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Neuigkeiten-Feed ist nicht eingerichtet (TENANCY_NEWS_FEED_URL, TENANCY_NEWS_FEED_TOKEN).');
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->withToken(config('tenancy.news_feed_token'))
            ->get(config('tenancy.news_feed_url'));

        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw new RuntimeException('Neuigkeiten-Feed nicht abrufbar (HTTP '.$response->status().').');
        }

        return $this->apply($response->json('data'));
    }

    /**
     * @param  list<array{id: int, title: string, body: string, min_plan: ?string, published_at: string, emailed: bool}>  $items
     * @return array{created: int, updated: int, deleted: int, emailed: int}
     */
    public function apply(array $items): array
    {
        $isFirstPull = ! VemaNews::query()->exists();
        $result = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'emailed' => 0];
        $remoteIds = [];

        DB::transaction(function () use ($items, $isFirstPull, &$result, &$remoteIds) {
            foreach ($items as $item) {
                $remoteIds[] = (int) $item['id'];

                $news = VemaNews::query()->firstOrNew(['remote_id' => (int) $item['id']]);
                $news->fill([
                    'title' => mb_substr((string) $item['title'], 0, 150),
                    'body' => (string) $item['body'],
                    'min_plan' => $item['min_plan'] ?? null,
                    'published_at' => Carbon::parse($item['published_at']),
                ]);

                if (! $news->exists && $isFirstPull) {
                    $news->emailed_at = now();
                }

                if ($news->isDirty()) {
                    $result[$news->exists ? 'updated' : 'created']++;
                    $news->save();
                }
            }

            $result['deleted'] = VemaNews::query()->whereNotIn('remote_id', $remoteIds)->delete();
        });

        $emailedRemoteIds = collect($items)->filter(fn (array $item) => (bool) ($item['emailed'] ?? false))->pluck('id')->all();

        VemaNews::query()
            ->whereNull('emailed_at')
            ->whereIn('remote_id', $emailedRemoteIds)
            ->orderBy('published_at')
            ->get()
            ->each(function (VemaNews $news) use (&$result) {
                $this->mailToAdmins($news);
                $news->update(['emailed_at' => now()]);
                $result['emailed']++;
            });

        return $result;
    }

    private function mailToAdmins(VemaNews $news): void
    {
        $recipients = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->unique();

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new VemaNewsMail($news));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Log::info('[Neuigkeiten] per E-Mail an Admins versendet', ['news' => $news->title, 'recipients' => $recipients->count()]);
    }
}
