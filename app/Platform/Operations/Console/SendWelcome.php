<?php

namespace App\Platform\Operations\Console;

use App\Domains\Accounts\Models\User;
use App\Platform\Operations\Installation\Application\InstallationState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

/**
 * Emails a user a link to set their password: the handover mail a hosting
 * provider sends once it has installed an instance with a random password
 * (`invoiceshelf:install --admin-password-random`). Unlike `--send-welcome`
 * on the install, a mail that is not sent fails the command, so the caller
 * can retry or report it.
 */
class SendWelcome extends Command
{
    protected $signature = 'invoiceshelf:send-welcome
        {--email= : The user who gets the link}';

    protected $description = 'Email a user a link to set their password';

    public function handle(): int
    {
        if (! InstallationState::isComplete()) {
            $this->components->error('InvoiceShelf is not installed yet.');

            return self::FAILURE;
        }

        $email = (string) $this->option('email');
        if (! User::query()->where('email', $email)->exists()) {
            $this->components->error('No user has that email.');

            return self::FAILURE;
        }

        try {
            $status = Password::broker()->sendResetLink(['email' => $email]);
        } catch (\Throwable $failure) {
            $this->components->error("The welcome mail could not be sent: {$failure->getMessage()}");

            return self::FAILURE;
        }

        if ($status !== Password::RESET_LINK_SENT) {
            $this->components->error("The welcome mail was not sent ({$status}).");

            return self::FAILURE;
        }

        $this->components->info("A link to set the password went to {$email}.");

        return self::SUCCESS;
    }
}
