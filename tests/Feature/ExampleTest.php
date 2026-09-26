<?php

test('guests are redirected from the start page to the login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

test('the login page renders on a freshly migrated database', function () {
    $this->get(route('login'))->assertOk();
});
