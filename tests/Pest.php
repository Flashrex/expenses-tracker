<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Feature');
