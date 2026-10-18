<?php

use App\Models\User;

it('serves the Vue app for its screens', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('<div id="app"></div>', false);
})->with(['/', '/signup', '/history', '/settings/anything']);

it('leaves API paths to the API, even ones that do not exist', function () {
    $this->getJson('/api/no-such-thing')->assertNotFound()->assertJsonStructure(['message']);
});

it('leaves email links and the health check to their own routes', function () {
    $user = User::factory()->create();

    $this->get("/email/preferences/{$user->id}")->assertForbidden()->assertDontSee('<div id="app"></div>', false);
    $this->get('/up')->assertOk()->assertDontSee('<div id="app"></div>', false);
});
