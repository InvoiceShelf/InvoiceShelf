<?php

test('.env.example ships no APP_KEY', function () {
    // Until 2.4.4 it carried this one, and the Docker image kept it, so every
    // Docker install without a key of its own ran on the same public key.
    expect(file_get_contents(base_path('.env.example')))
        ->toContain("\nAPP_KEY=\n")
        ->not->toContain('base64:kgk/4DW1vEVy7aEvet5FPp5un6PIGe/so8H0mvoUtW0=');
});
