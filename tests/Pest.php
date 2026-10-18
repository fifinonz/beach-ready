<?php

use Tests\TestCase;

/*
| Feature tests boot the Laravel application through Tests\TestCase.
| Unit tests run against plain PHPUnit and never touch the framework.
*/

pest()->extend(TestCase::class)->in('Feature');
