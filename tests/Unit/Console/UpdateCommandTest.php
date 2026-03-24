<?php

use App\Console\Commands\UpdateCommand;

it('reads installed version from version file', function () {
    $command = app(UpdateCommand::class);
    $expectedVersion = preg_replace('~[\r\n]+~', '', file_get_contents(base_path('version.md')));

    expect($command->getInstalledVersion())->toBe($expectedVersion);
});
