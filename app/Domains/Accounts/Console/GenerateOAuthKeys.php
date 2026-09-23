<?php

namespace App\Domains\Accounts\Console;

use App\Domains\Accounts\Application\AccessRevoker;
use App\Domains\Accounts\Application\OAuth\OAuthKeyManager;
use Illuminate\Console\Command;

/**
 * Create, or replace, the key pair the OAuth server signs tokens with.
 *
 * `--if-missing` is what the container entrypoint runs on every start: it
 * does nothing when keys are available, from files or from the environment.
 * `--force` replaces existing files and revokes every issued token, since
 * none of them can be verified with the new key.
 */
class GenerateOAuthKeys extends Command
{
    protected $signature = 'oauth:keys
        {--if-missing : Only create keys when none are available}
        {--force : Replace existing key files and revoke every issued token}';

    protected $description = 'Create the OAuth server signing keys.';

    public function handle(OAuthKeyManager $keys, AccessRevoker $revoker): int
    {
        $status = $keys->status();

        if ($status === OAuthKeyManager::SOURCE_ENV) {
            $this->components->info('OAuth keys are taken from PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY; nothing to do.');

            return self::SUCCESS;
        }

        if ($status === OAuthKeyManager::SOURCE_FILE && ! $this->option('force')) {
            if ($this->option('if-missing')) {
                return self::SUCCESS;
            }

            $this->components->error('OAuth keys already exist. Use --force to replace them, which signs every connected client out.');

            return self::FAILURE;
        }

        $keys->regenerate();

        if ($status === OAuthKeyManager::SOURCE_FILE) {
            $revoker->revokeEverything();
            $this->components->warn('Every issued OAuth token was revoked; connected clients must sign in again.');
        }

        $this->components->info('OAuth keys written to '.dirname($keys->privateKeyPath()).'.');

        return self::SUCCESS;
    }
}
