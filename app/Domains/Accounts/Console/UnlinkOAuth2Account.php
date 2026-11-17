<?php

namespace App\Domains\Accounts\Console;

use App\Domains\Accounts\Application\OAuth2LoginService;
use Illuminate\Console\Command;

/**
 * Removes the link between a user and their account at the identity provider
 * that sign-in with SSO uses, for example after that account was deleted and
 * created again. The user's next sign-in with SSO links them again by email
 * address.
 */
class UnlinkOAuth2Account extends Command
{
    /**
     * @var string
     */
    protected $signature = 'oauth2:unlink {email : The email address of the InvoiceShelf user}';

    /**
     * @var string
     */
    protected $description = 'Unlink a user from their SSO account, so that their next sign-in with SSO links them again by email address';

    public function handle(OAuth2LoginService $accounts): int
    {
        $email = (string) $this->argument('email');
        $user = $accounts->userWithEmail($email);

        if (! $user) {
            $this->error("No user has the email address {$email}.");

            return self::FAILURE;
        }

        if (! $accounts->unlink($user)) {
            $this->info("{$user->email} is not linked to an SSO account.");

            return self::SUCCESS;
        }

        $this->info("Unlinked {$user->email} from their SSO account.");

        return self::SUCCESS;
    }
}
