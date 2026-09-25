<?php

/*
|--------------------------------------------------------------------------
| Private network exemptions
|--------------------------------------------------------------------------
|
| Where a setting names a host the server will connect to, InvoiceShelf
| refuses private, loopback, link-local and other reserved addresses
| (App\Support\Net\PrivateNetworkGuard), so a settings screen cannot aim the
| server at internal services. A feature that legitimately talks to a host
| on your network gets that host named here, for that feature only.
|
| An entry with a scheme ("http://pdf:3000") exempts exactly that URL; a bare
| entry ("mail.lan", "192.168.1.10") exempts that host. Nothing is exempt by
| default, and there is no way to exempt everything.
|
*/

return [

    'allowed_private_hosts' => [

        // The Gotenberg PDF renderer, usually a sidecar. Its response is sent
        // back as the PDF, so exactly one URL may be named:
        // GOTENBERG_ALLOWED_PRIVATE_HOST=http://pdf:3000
        'gotenberg' => array_values(array_filter([trim((string) env('GOTENBERG_ALLOWED_PRIVATE_HOST', ''))])),

        // Mail relays that company owners (not only the super administrator)
        // may use, comma separated: MAIL_ALLOWED_PRIVATE_HOSTS=mail.lan,192.168.1.10
        'mail' => array_values(array_filter(array_map(
            fn (string $host): string => strtolower(trim($host, " \t[]")),
            explode(',', (string) env('MAIL_ALLOWED_PRIVATE_HOSTS', '')),
        ))),

    ],

];
