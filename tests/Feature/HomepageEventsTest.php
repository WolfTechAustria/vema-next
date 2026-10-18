<?php

use App\Enums\HomepageSectionType;
use App\Enums\HomepageTemplate;
use App\Livewire\Events\Index as EventsIndex;
use App\Livewire\Homepage\Sections;
use App\Livewire\Homepage\Settings;
use App\Models\ClubEvent;
use App\Models\ClubEventSource;
use App\Models\HomepagePage;
use App\Models\HomepageSection;
use App\Models\HomepageSetting;
use App\Models\Member;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function enableHomepageWithEvents(array $attributes = []): void
{
    HomepageSetting::current()->update(array_merge(['enabled' => true, 'show_events' => true], $attributes));
}

describe('public events scope', function () {
    it('only includes released events without member restriction', function () {
        $public = ClubEvent::factory()->onHomepage()->create(['title' => 'Sommerfest']);
        ClubEvent::factory()->create(['title' => 'Vorstandssitzung']);

        $restricted = ClubEvent::factory()->onHomepage()->create(['title' => 'Nur Vorstand']);
        $restricted->members()->attach(Member::create(['gender' => 'm', 'name' => 'Max', 'surname' => 'Muster', 'active' => 1])->memberID);

        expect(ClubEvent::query()->onHomepage()->pluck('title')->all())->toBe(['Sommerfest'])
            ->and($public->fresh()->show_on_homepage)->toBeTrue();
    });

    it('includes events of released, active external sources', function () {
        $released = ClubEventSource::factory()->onHomepage()->create();
        $hidden = ClubEventSource::factory()->create();
        $inactive = ClubEventSource::factory()->onHomepage()->inactive()->create();

        ClubEvent::factory()->create(['title' => 'Bezirksschießen', 'sourceID' => $released->sourceID, 'external_uid' => 'a']);
        ClubEvent::factory()->create(['title' => 'Intern extern', 'sourceID' => $hidden->sourceID, 'external_uid' => 'b']);
        ClubEvent::factory()->create(['title' => 'Inaktiv', 'sourceID' => $inactive->sourceID, 'external_uid' => 'c']);

        expect(ClubEvent::query()->onHomepage()->pluck('title')->all())->toBe(['Bezirksschießen']);
    });
});

describe('staff', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->admin()->create());
    });

    it('releases an event for the homepage', function () {
        Livewire::test(EventsIndex::class)
            ->set('title', 'Dorfmeisterschaft')
            ->set('startDate', now()->addWeek()->format('Y-m-d'))
            ->set('startTime', '19:00')
            ->set('showOnHomepage', true)
            ->call('saveEvent')
            ->assertHasNoErrors();

        $event = ClubEvent::sole();

        expect($event->show_on_homepage)->toBeTrue();

        Livewire::test(EventsIndex::class)
            ->call('editEvent', $event->eventID)
            ->assertSet('showOnHomepage', true);
    });

    it('releases an external source for the homepage', function () {
        $source = ClubEventSource::factory()->inactive()->create();

        Livewire::test(EventsIndex::class)
            ->call('editSource', $source->sourceID)
            ->set('sourceShowOnHomepage', true)
            ->call('saveSource');

        expect($source->fresh()->show_on_homepage)->toBeTrue();
    });

    it('switches the events page off in the homepage settings', function () {
        $editor = User::factory()->create();
        $editor->assignRole(Role::findOrCreate('homepage', 'web'));

        Livewire::actingAs($editor)
            ->test(Settings::class)
            ->set('showEvents', false)
            ->call('save')
            ->assertHasNoErrors();

        expect(HomepageSetting::current()->show_events)->toBeFalse();
    });
});

describe('homepage', function () {
    it('lists upcoming and past public events in every template', function (HomepageTemplate $template) {
        enableHomepageWithEvents(['template' => $template]);
        ClubEvent::factory()->onHomepage()->create(['title' => 'Sommerfest', 'location' => 'Schützenheim', 'description' => 'Für alle Gäste']);
        ClubEvent::factory()->onHomepage()->past()->create(['title' => 'Osterschießen']);
        ClubEvent::factory()->create(['title' => 'Vorstandssitzung']);

        $this->get(route('homepage.events'))
            ->assertOk()
            ->assertSeeInOrder(['Sommerfest', 'Schützenheim', 'Für alle Gäste', 'Vergangene Termine', 'Osterschießen'])
            ->assertDontSee('Vorstandssitzung');

        $this->get(route('homepage.home'))
            ->assertOk()
            ->assertSee('Nächste Termine')
            ->assertSee('Sommerfest')
            ->assertSee(route('homepage.events'));
    })->with(HomepageTemplate::cases());

    it('hides the events page and the home block when switched off', function () {
        enableHomepageWithEvents(['show_events' => false]);
        ClubEvent::factory()->onHomepage()->create(['title' => 'Sommerfest']);

        $this->get(route('homepage.events'))->assertNotFound();
        $this->get(route('homepage.home'))->assertOk()->assertDontSee('Nächste Termine')->assertDontSee(route('homepage.events'));
    });

    it('shows no home block without upcoming public events', function () {
        enableHomepageWithEvents();
        ClubEvent::factory()->create(['title' => 'Vorstandssitzung']);

        $this->get(route('homepage.home'))->assertOk()->assertDontSee('Nächste Termine');
    });

    it('renders the events section with its limit', function () {
        enableHomepageWithEvents();
        $page = HomepagePage::factory()->create(['slug' => 'training']);
        HomepageSection::factory()->for($page, 'page')->create(['type' => HomepageSectionType::Events, 'heading' => 'Unsere Termine', 'data' => ['limit' => 2]]);

        foreach (['Erster', 'Zweiter', 'Dritter'] as $days => $title) {
            ClubEvent::factory()->onHomepage()->create(['title' => $title.' Termin', 'starts_at' => now()->addDays($days + 1)->setTime(19, 0), 'ends_at' => null]);
        }

        $this->get(route('homepage.page', 'training'))
            ->assertOk()
            ->assertSeeInOrder(['Unsere Termine', 'Erster Termin', 'Zweiter Termin'])
            ->assertDontSee('Dritter Termin');
    });

    it('stores the limit of an events section', function () {
        $editor = User::factory()->create();
        $editor->assignRole(Role::findOrCreate('homepage', 'web'));
        $page = HomepagePage::factory()->create();

        Livewire::actingAs($editor)
            ->test(Sections::class, ['page' => $page])
            ->call('addSection', 'events')
            ->set('eventLimit', 3)
            ->call('saveSection')
            ->assertHasNoErrors();

        expect(HomepageSection::sole()->value('limit'))->toBe(3);
    });
});
