<?php

namespace App\Services;

use App\Models\ClubEvent;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Strukturierte Daten (schema.org als JSON-LD) für Suchmaschinen: Verein,
 * Beiträge und Termine — Google zeigt z. B. Termine direkt in den Ergebnissen.
 */
class HomepageStructuredData
{
    public function __construct(
        private HomepageHosts $hosts,
        private HomepageImageUrls $imageUrls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function organization(Setting $club): array
    {
        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $club->street,
            'postalCode' => $club->zip,
            'addressLocality' => $club->city,
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $club->name,
            'url' => $this->hosts->publicRoute('homepage.home'),
            'logo' => $this->imageUrls->logo(),
            'email' => $club->email,
            'telephone' => $club->phone,
            'address' => count($address) > 1 ? $address : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function article(Post $post, Setting $club, ?string $description): array
    {
        $publisher = array_filter([
            '@type' => 'Organization',
            'name' => $club->name,
            'logo' => $this->imageUrls->logo(),
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => Str::limit($post->title, 110, ''),
            'description' => $description,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
            'image' => $post->images->take(3)->map(fn ($image) => $this->imageUrls->postImage($image, 'gross'))->values()->all() ?: null,
            'mainEntityOfPage' => $this->hosts->publicRoute('homepage.posts.show', ['slug' => $post->slug]),
            'author' => $publisher,
            'publisher' => $publisher,
        ]);
    }

    /**
     * @param  Collection<int, ClubEvent>  $events
     * @return list<array<string, mixed>>
     */
    public function events(Collection $events, Setting $club): array
    {
        return $events->map(fn (ClubEvent $event) => $this->event($event, $club))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function event(ClubEvent $event, Setting $club): array
    {
        $format = $event->all_day ? 'Y-m-d' : DATE_ATOM;
        $clubAddress = collect([$club->street, trim($club->zip.' '.$club->city)])->filter()->implode(', ');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'description' => filled($event->description) ? Str::limit(trim(strip_tags((string) $event->description)), 300) : null,
            'startDate' => $event->starts_at->format($format),
            'endDate' => $event->ends_at?->format($format),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => array_filter([
                '@type' => 'Place',
                'name' => $event->location ?: $club->name,
                'address' => $event->location ?: ($clubAddress ?: $club->name),
            ]),
            'organizer' => [
                '@type' => 'Organization',
                'name' => $club->name,
                'url' => $this->hosts->publicRoute('homepage.home'),
            ],
            'url' => $this->hosts->publicRoute('homepage.events'),
        ]);
    }
}
