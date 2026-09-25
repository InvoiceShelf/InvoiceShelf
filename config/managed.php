<?php

/*
|--------------------------------------------------------------------------
| Managed hosting
|--------------------------------------------------------------------------
|
| An install run for its owner by a hosting provider, such as InvoiceShelf
| Cloud. The provider handles storage, backups, PDF rendering, the server's
| own mail transport, modules and upgrades, so those settings are locked and
| hidden. Kept out of config('invoiceshelf'), which the SPA bootstrap sends
| to every signed-in member.
|
*/

return [
    'enabled' => (bool) env('INVOICESHELF_MANAGED', false),

    // Where the owner asks the provider for help, shown instead of the
    // locked settings.
    'support_url' => env('INVOICESHELF_SUPPORT_URL'),
];
