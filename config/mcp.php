<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect domains
    |--------------------------------------------------------------------------
    |
    | The origins an AI client may register as its OAuth redirect when it
    | registers itself (dynamic client registration). An administrator can
    | add more from Administration, but a wildcard is never accepted: a
    | registration that could redirect anywhere would let any site collect
    | authorization codes. Loopback addresses allow any port, which is how
    | Claude Code and the MCP Inspector receive their callbacks.
    |
    */

    'redirect_domains' => [
        'https://claude.ai',
        'https://claude.com',
        'https://chatgpt.com',
        'http://localhost',
        'http://127.0.0.1',
        'http://[::1]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom schemes
    |--------------------------------------------------------------------------
    |
    | Desktop clients that receive the redirect through a private URI scheme
    | (RFC 8252) instead of a web address.
    |
    */

    'custom_schemes' => [
        'cursor',
        'vscode',
        'vscode-insiders',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization server
    |--------------------------------------------------------------------------
    |
    | The issuer advertised in the discovery documents. Null means this
    | installation's own URL, which is always the case here.
    |
    */

    'authorization_server' => null,

];
