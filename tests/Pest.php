<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Unit');

// The module-system tests scaffold real modules on disk (Modules/ScaffoldProbe) and
// toggle the shared modules_statuses.json — global, filesystem-level state that paratest
// does NOT isolate per worker (it only isolates the database). Tag them so CI can run
// this group serially, after the parallel pass, to avoid cross-worker collisions.
uses()->group('modules')->in('Feature/Company/Modules');
