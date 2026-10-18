<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the Laravel application through Tests\TestCase and run
| against the beach_ready_testing PostgreSQL database, migrated fresh and
| wrapped in a transaction per test. Unit tests never touch the framework.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
