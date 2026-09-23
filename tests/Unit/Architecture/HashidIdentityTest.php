<?php

use App\Facades\Hashids as HashidsFacade;
use App\Support\Hashids\HashidConnection;
use Hashids\Hashids;

test('hashid connections retain their historical salts', function (HashidConnection $connection, string $legacyClass) {
    $config = config("hashids.connections.{$connection->value}");

    expect($config)->toBeArray()
        ->and($config['salt'])->toBe($legacyClass.config('app.key'));

    $legacy = new Hashids($legacyClass.config('app.key'), $config['length'], $config['alphabet']);

    expect(HashidsFacade::connection($connection->value)->encode(1))->toBe($legacy->encode(1));
})->with([
    [HashidConnection::Company, 'App\\Models\\Company'],
]);
