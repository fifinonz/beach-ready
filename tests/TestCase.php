<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The SPA calls the API from the app's own origin, which is what makes
        // Sanctum start a session. Test requests do the same.
        $this->withHeader('Referer', config('app.url'));
    }
}
