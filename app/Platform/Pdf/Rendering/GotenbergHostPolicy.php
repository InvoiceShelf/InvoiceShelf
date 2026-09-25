<?php

namespace App\Platform\Pdf\Rendering;

use App\Support\Net\PrivateNetworkGuard;

/**
 * Decides whether a Gotenberg host is exempt from {@see PrivateNetworkGuard}.
 *
 * Gotenberg is normally deployed as a sidecar on a private network — the shipped
 * default host is `http://pdf:3000` — which the SSRF guard rejects. The exemption
 * is declared in the environment and names the single host it trusts:
 *
 *     GOTENBERG_ALLOWED_PRIVATE_HOST=http://pdf:3000
 *
 * It is deliberately NOT a boolean and deliberately not settable from the admin UI.
 * `gotenberg_host` itself stays editable by any super admin, and the driver returns
 * the upstream response body verbatim as the PDF — so a blanket "allow private"
 * switch would let that setting be repointed at a link-local metadata endpoint and
 * read back the response. Matching one declared host keeps the sidecar working while
 * every other private target stays blocked.
 *
 * Both the save-time validation rule and the runtime driver guard call this, so the
 * two layers cannot drift apart. The matching itself is the shared
 * {@see PrivateNetworkGuard::isExempt()}, fed by config/network.php.
 */
class GotenbergHostPolicy
{
    /**
     * Whether the given host is the operator-declared Gotenberg host, and may
     * therefore skip the private-network check.
     */
    public static function isExemptFromPrivateNetworkGuard(?string $host): bool
    {
        return PrivateNetworkGuard::isExempt('gotenberg', $host);
    }
}
