<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Application\Exceptions\SsoSignInRefused;
use App\Domains\Accounts\Models\SsoIdentity;
use App\Domains\Accounts\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser as OidcAccount;

/**
 * Which user an OpenID Connect account signs in as.
 *
 * Only existing users can sign in with SSO; it never creates one. The first
 * time an account signs in, it is linked to the one user with the same email
 * address, provided the identity provider says it verified that address
 * (OIDC_REQUIRE_VERIFIED_EMAIL). The link is an SsoIdentity of type "oidc"
 * naming the account's issuer and its "sub" claim, and from then on only it
 * counts: changing either email address later does not break it, no other
 * account can take the user over, and the same "sub" from another issuer is
 * a different account. `sso:unlink` removes links.
 */
final class OidcLoginService
{
    /**
     * The user the account signs in as: the user linked to it, or on its
     * first sign-in the one user with its email address, which this then
     * links to it.
     *
     * @throws SsoSignInRefused when it may not sign in, saying why
     */
    public function userFor(OidcAccount $account): User
    {
        $subject = $account->getId();

        if ((! is_string($subject) && ! is_int($subject)) || (string) $subject === '') {
            throw new SsoSignInRefused('Sign-in with SSO refused: the identity provider sent no "sub" claim for the account.');
        }

        $subject = (string) $subject;
        $issuer = $this->issuerOf($account);
        $identity = $this->linkedIdentity($issuer, $subject);

        if ($identity) {
            $identity->forceFill(['last_used_at' => now()])->save();

            return $identity->user;
        }

        $email = Str::lower(trim((string) $account->getEmail()));
        $context = ['email' => $email, 'sso_type' => SsoIdentity::TYPE_OIDC, 'sso_issuer' => $issuer, 'sso_subject' => $subject];

        // Before looking for the user, so this answer says nothing about
        // whether a user has the address.
        if (OidcLogin::requiresVerifiedEmail() && ! $this->emailVerified($account)) {
            throw new SsoSignInRefused('Sign-in with SSO refused: the identity provider has not verified this email address ("email_verified" is not true), and the first sign-in links by email address. See OIDC_REQUIRE_VERIFIED_EMAIL.', SsoLogin::EMAIL_UNVERIFIED, $context);
        }

        $users = $email === ''
            ? collect()
            : User::query()->whereRaw('LOWER(email) = ?', [$email])->limit(2)->get();

        if ($users->count() !== 1) {
            throw new SsoSignInRefused('Sign-in with SSO refused: no single user has this email address.', SsoLogin::NO_ACCOUNT, $context);
        }

        $user = $users->first();

        if ($user->ssoIdentities()->where('type', SsoIdentity::TYPE_OIDC)->exists()) {
            throw $this->linkedElsewhere($user, $context);
        }

        return $this->link($user, $issuer, $subject, $context);
    }

    /**
     * Links the account to the user on its first sign-in.
     *
     * Two first sign-ins may pass the checks above at the same moment, for
     * the same account or for two accounts with the user's address. The
     * unique indexes on sso_identities let only one insert through: an
     * account links to one user (type, issuer, subject) and a user to one
     * account per protocol (user_id, type). The other insert fails, and this
     * signs in only if the row that won is this very link.
     *
     * @param  array<string, mixed>  $context
     */
    private function link(User $user, string $issuer, string $subject, array $context): User
    {
        try {
            // In a transaction of its own, so a refused insert rolls back to
            // here and leaves any surrounding transaction usable (PostgreSQL
            // aborts the whole transaction on a failed statement).
            DB::transaction(fn () => $user->ssoIdentities()->create([
                'type' => SsoIdentity::TYPE_OIDC,
                'issuer' => $issuer,
                'subject' => $subject,
                'last_used_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            if ($this->linkedIdentity($issuer, $subject)?->user_id !== $user->id) {
                throw $this->linkedElsewhere($user, $context);
            }

            return $user;
        }

        Log::info('Linked a user to their account at the identity provider on their first sign-in with SSO.', [
            'user_id' => $user->id,
            'sso_type' => SsoIdentity::TYPE_OIDC,
            'sso_issuer' => $issuer,
            'sso_subject' => $subject,
        ]);

        return $user;
    }

    /**
     * The link to the account, if there is one.
     *
     * The database narrows it down, PHP decides. MySQL's usual collations
     * compare without case or accents, so the query may also find "alice"
     * for "Alice", and that is not the same account.
     */
    private function linkedIdentity(string $issuer, string $subject): ?SsoIdentity
    {
        return SsoIdentity::query()
            ->where('type', SsoIdentity::TYPE_OIDC)
            ->where('issuer', $issuer)
            ->where('subject', $subject)
            ->with('user')
            ->get()
            ->first(fn (SsoIdentity $identity): bool => $identity->type === SsoIdentity::TYPE_OIDC
                && $identity->issuer === $issuer
                && $identity->subject === $subject);
    }

    /**
     * The refusal for a user who is linked to another account already.
     *
     * @param  array<string, mixed>  $context
     */
    private function linkedElsewhere(User $user, array $context): SsoSignInRefused
    {
        return new SsoSignInRefused('Sign-in with SSO refused: the user with this email address is linked to another account. `php artisan sso:unlink` removes that link.', SsoLogin::NO_ACCOUNT, [
            'user_id' => $user->id,
            ...$context,
        ]);
    }

    /**
     * The identity provider the account belongs to, as the link records it:
     * the ID token's issuer, or else the configured one, or else the scheme
     * and host of the token endpoint. Without a trailing slash, so the same
     * issuer always reads the same.
     */
    private function issuerOf(OidcAccount $account): string
    {
        $issuer = $this->idTokenClaims($account)['iss'] ?? null;

        if (! is_string($issuer) || $issuer === '') {
            $issuer = OidcLogin::issuer() ?? $this->originOf((string) config('services.oidc.token_url'));
        }

        return OidcLogin::withoutTrailingSlash($issuer);
    }

    /**
     * The URL's scheme, host and port.
     */
    private function originOf(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * Whether the identity provider says it verified the account's email
     * address: the userinfo answer's `email_verified`, or the ID token's when
     * the userinfo answer has none. Some providers send it as a string.
     */
    private function emailVerified(OidcAccount $account): bool
    {
        $claims = $account->getRaw();
        $verified = array_key_exists('email_verified', $claims)
            ? $claims['email_verified']
            : ($this->idTokenClaims($account)['email_verified'] ?? null);

        return $verified === true || $verified === 'true';
    }

    /**
     * The claims of the account's ID token, if the token endpoint sent one
     * (OidcProvider checked them).
     *
     * @return array<string, mixed>
     */
    private function idTokenClaims(OidcAccount $account): array
    {
        $claims = $account->attributes['id_token'] ?? null;

        return is_array($claims) ? $claims : [];
    }
}
