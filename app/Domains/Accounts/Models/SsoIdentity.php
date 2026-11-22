<?php

namespace App\Domains\Accounts\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account at an outside identity provider that signs a user in (single
 * sign-on). `type` is the protocol, `issuer` and `subject` name the account
 * there, and `last_used_at` is its latest sign-in. An account belongs to one
 * user, and a user has one account per protocol.
 */
class SsoIdentity extends Model
{
    use HasFactory;

    /**
     * An account at an OpenID Connect provider: `issuer` is its issuer URL,
     * without a trailing slash, and `subject` its "sub" claim.
     */
    public const TYPE_OIDC = 'oidc';

    protected $table = 'sso_identities';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * The user this account signs in as.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
