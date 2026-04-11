<?php

test('module configuration route under company settings is owner-only', function () {
    $contents = file_get_contents(base_path('resources/scripts/features/company/settings/routes.ts'));

    expect($contents)->not->toBeFalse();

    // The settings.modules child route must declare isOwner so non-owners can't
    // reach the Module Configuration page even if they hit the URL directly.
    expect($contents)->toMatch("/name:\\s*'settings\\.modules'[\\s\\S]*?isOwner:\\s*true/");

    // The frontend route deliberately does not duplicate the backend ability
    // check — that's enforced server-side via the menu filter and the
    // controller-level Bouncer policy.
    expect($contents)->not->toContain("ability: 'manage-module'");
    expect($contents)->not->toContain("ability: 'manage modules'");
});
