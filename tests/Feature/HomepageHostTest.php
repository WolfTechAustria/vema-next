<?php

use App\Models\HomepageSetting;
use App\Models\Post;
use App\Services\HomepageHosts;

describe('single installation', function () {
    beforeEach(function () {
        HomepageSetting::current()->update(['enabled' => true, 'domain' => 'mv-angerberg.at']);
        app(HomepageHosts::class)->forgetSingleDomain();
    });

    it('serves the homepage on its own domain without the /site prefix', function () {
        $post = Post::factory()->create(['title' => 'Konzertbericht']);

        $this->get('http://mv-angerberg.at/')
            ->assertOk()
            ->assertSee('Konzertbericht')
            ->assertSee('http://mv-angerberg.at/news/'.$post->slug, false)
            ->assertDontSee('/site/', false);

        $this->get('http://mv-angerberg.at/news/'.$post->slug)->assertOk();
    });

    it('redirects www to the domain without www', function () {
        $this->get('http://www.mv-angerberg.at/news?jahr=2025')
            ->assertStatus(301)
            ->assertRedirect('http://mv-angerberg.at/news?jahr=2025');
    });

    it('does not expose the app on the homepage domain', function () {
        $this->get('http://mv-angerberg.at/login')->assertNotFound();
        $this->get('http://mv-angerberg.at/dashboard')->assertNotFound();
    });

    it('keeps the app on its own host', function () {
        $this->get('http://localhost/login')->assertOk();
    });
});
