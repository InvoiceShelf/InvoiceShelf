<?php

namespace App\Domains\Accounts\Application\OAuth;

use Laravel\Passport\Passport;
use phpseclib4\Crypt\RSA;

/**
 * The key pair the OAuth server signs access tokens with.
 *
 * Keys come from `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` when those
 * are set, which is the only arrangement that works across several replicas.
 * Otherwise they are files in storage/, created on demand. Whoever creates the
 * files owns them, and Passport refuses a private key other users can read, so
 * files are best created by the process that serves requests: the web request
 * that switches a consumer on, or the container entrypoint.
 */
class OAuthKeyManager
{
    public const SOURCE_ENV = 'env';

    public const SOURCE_FILE = 'file';

    public const SOURCE_MISSING = 'missing';

    /**
     * Where the keys currently come from, or `missing` when the server could
     * not sign or verify a token right now.
     */
    public function status(): string
    {
        if (filled(config('passport.private_key')) && filled(config('passport.public_key'))) {
            return self::SOURCE_ENV;
        }

        return is_readable($this->privateKeyPath()) && is_readable($this->publicKeyPath())
            ? self::SOURCE_FILE
            : self::SOURCE_MISSING;
    }

    /**
     * Whether tokens can be signed and verified.
     */
    public function ready(): bool
    {
        return $this->status() !== self::SOURCE_MISSING;
    }

    /**
     * Create the key files unless keys are already available.
     *
     * @return bool true when files were written by this call
     */
    public function ensure(): bool
    {
        if ($this->ready()) {
            return false;
        }

        $this->write();

        return true;
    }

    /**
     * Replace the key files with a new pair.
     *
     * Every token signed with the old key stops verifying, so callers are
     * expected to revoke the stored tokens as well (see AccessRevoker).
     * Keys taken from the environment are not touched.
     */
    public function regenerate(): void
    {
        $this->write();
    }

    public function privateKeyPath(): string
    {
        return Passport::keyPath('oauth-private.key');
    }

    public function publicKeyPath(): string
    {
        return Passport::keyPath('oauth-public.key');
    }

    /**
     * Mirrors `passport:keys`: a 4096-bit RSA pair, the private half readable
     * by its owner only, which Passport's permission check requires.
     */
    private function write(int $bits = 4096): void
    {
        $key = RSA::createKey($bits);

        file_put_contents($this->publicKeyPath(), (string) $key->getPublicKey());
        file_put_contents($this->privateKeyPath(), (string) $key);

        if (! windows_os()) {
            chmod($this->publicKeyPath(), 0660);
            chmod($this->privateKeyPath(), 0600);
        }
    }
}
