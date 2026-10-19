<?php

use App\Models\User;

it('logs a user in with the right password', function () {
    $user = User::factory()->create(['password' => 'a-long-password']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'a-long-password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('accepts the email in any case', function () {
    $user = User::factory()->create(['email' => 'muthoni@example.com', 'password' => 'a-long-password']);

    $this->postJson('/api/login', ['email' => 'Muthoni@Example.com', 'password' => 'a-long-password'])
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('refuses the wrong password', function () {
    $user = User::factory()->create(['password' => 'a-long-password']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'not-the-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('logs a user out', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/logout')->assertNoContent();

    $this->assertGuest('web');
});

it('returns the current user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'email' => $user->email,
            'timezone' => $user->timezone,
            'email_confirmed' => false,
            'fasting_hours' => null,
            'why' => null,
            'member_since' => $user->created_at->toIso8601ZuluString(),
            'is_demo' => false,
        ]]);
});

it('refuses guests', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/user'],
    ['POST', '/api/logout'],
]);
