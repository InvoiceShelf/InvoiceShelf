<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Removes the link between a user and their account at the identity provider
 * that sign-in with SSO uses, for example after that account was deleted and
 * created again. The user's next sign-in with SSO links them again by email
 * address.
 */
class UnlinkOAuth2Account extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oauth2:unlink {email : The email address of the InvoiceShelf user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlink a user from their SSO account, so that their next sign-in with SSO links them again by email address';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error("No user has the email address {$email}.");

            return self::FAILURE;
        }

        if ($user->oauth2_id === null) {
            $this->info("{$user->email} is not linked to an SSO account.");

            return self::SUCCESS;
        }

        $user->forceFill(['oauth2_id' => null])->save();

        $this->info("Unlinked {$user->email} from their SSO account.");

        return self::SUCCESS;
    }
}
