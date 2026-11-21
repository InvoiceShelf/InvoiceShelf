<?php

namespace App\Domains\Accounts\Console;

use App\Domains\Accounts\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Removes the links between a user and their accounts at the identity
 * providers that single sign-on uses, for example after an account was
 * deleted and created again there, or before moving to another identity
 * provider. The user's next sign-in with SSO links them again by email
 * address.
 */
class UnlinkSsoAccount extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sso:unlink
        {email : The email address of the InvoiceShelf user}
        {--type= : Only the links of this sign-on protocol, such as oidc}';

    /**
     * @var string
     */
    protected $description = 'Unlink a user from their SSO accounts, so that their next sign-in with SSO links them again by email address';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $type = $this->option('type');
        $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])->first();

        if (! $user) {
            $this->error("No user has the email address {$email}.");

            return self::FAILURE;
        }

        $removed = $user->ssoIdentities()
            ->when(filled($type), fn ($query) => $query->where('type', $type))
            ->delete();

        if ($removed === 0) {
            $this->info("{$user->email} is not linked to an SSO account".(filled($type) ? " of type {$type}." : '.'));

            return self::SUCCESS;
        }

        $this->info("Unlinked {$user->email} from {$removed} SSO account(s).");

        return self::SUCCESS;
    }
}
