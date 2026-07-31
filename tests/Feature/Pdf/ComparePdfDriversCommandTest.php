<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Console\Command\Command;

/**
 * The command itself needs a live Gotenberg, so what is asserted here is the
 * behaviour around that: it refuses clearly when the service is absent, and it
 * leaves the operator's data alone, since comparing designs means persisting a
 * template change the services will actually re-read.
 */
beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    Sanctum::actingAs(User::find(1), ['*']);
});

test('it refuses with a usable message when gotenberg is unreachable', function () {
    config(['pdf.connections.gotenberg.host' => 'http://127.0.0.1:1']);

    $exit = Artisan::call('pdf:compare');

    expect($exit)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('not reachable');
});

test('it does not change the documents it compares', function () {
    config(['pdf.connections.gotenberg.host' => 'http://127.0.0.1:1']);

    $before = Invoice::orderBy('id')->pluck('template_name', 'id')->all();

    Artisan::call('pdf:compare');

    expect(Invoice::orderBy('id')->pluck('template_name', 'id')->all())->toBe($before);
});
