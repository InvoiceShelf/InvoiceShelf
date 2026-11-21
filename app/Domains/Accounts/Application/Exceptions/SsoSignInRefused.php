<?php

namespace App\Domains\Accounts\Application\Exceptions;

use App\Domains\Accounts\Application\SsoLogin;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * A single sign-on that cannot go on. The message is for the log and says
 * why; `error` is what the login page or the mobile app tells the user (one
 * of the SsoLogin error codes).
 */
final class SsoSignInRefused extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $error = SsoLogin::FAILED,
        public readonly array $context = [],
        public readonly string $level = 'notice',
    ) {
        parent::__construct($message);
    }

    /**
     * A sign-in that cannot start or finish because of how the identity
     * provider is set up or reached, rather than because of the account.
     * Logged as a warning: an administrator has something to fix.
     *
     * @param  array<string, mixed>  $context
     */
    public static function misconfigured(string $message, array $context = []): self
    {
        return new self($message, SsoLogin::FAILED, $context, 'warning');
    }

    /**
     * Logs why the sign-in cannot go on, and returns what to tell the user.
     */
    public function logged(): string
    {
        Log::log($this->level, $this->getMessage(), $this->context);

        return $this->error;
    }
}
