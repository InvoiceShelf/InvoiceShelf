<?php

use App\Modules\Activators\SafeModeFileActivator;

it('disables all modules when MODULES_SAFE_MODE is enabled', function () {
    config()->set('modules.safe_mode', true);

    $activator = (new ReflectionClass(SafeModeFileActivator::class))
        ->newInstanceWithoutConstructor();

    expect($activator->isActive('Anything'))->toBeFalse();
});
