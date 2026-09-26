<?php

namespace App\Domains\Accounts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A role the super administrator defines once for the whole install.
 *
 * Every company holds a copy as an ordinary Bouncer role that its owner can
 * hand out but not change: `owner` for the built-in Owner preset, and
 * `preset:{key}` for the others, so a copy never collides with a role the
 * company made itself. The key is fixed when the preset is created and ties
 * the preset to its copies from then on.
 *
 * @property int $id
 * @property string $key
 * @property string $title
 * @property list<string>|null $abilities null for the Owner preset, which holds the whole catalogue
 */
class RolePreset extends Model
{
    /** The built-in preset behind every company's `owner` role. */
    public const OWNER = 'owner';

    /** How the name of a company's copy of any other preset starts. */
    public const ROLE_PREFIX = 'preset:';

    protected $table = 'role_presets';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
        ];
    }

    public function isOwner(): bool
    {
        return $this->key === self::OWNER;
    }

    /**
     * The name of this preset's copy inside a company.
     */
    public function roleName(): string
    {
        return self::roleNameFor($this->key);
    }

    public static function roleNameFor(string $key): string
    {
        return $key === self::OWNER ? self::OWNER : self::ROLE_PREFIX.$key;
    }

    /**
     * The preset a company role is a copy of, or null for a role the company
     * made itself.
     */
    public static function keyFromRoleName(?string $name): ?string
    {
        if ($name === self::OWNER) {
            return self::OWNER;
        }

        if ($name !== null && str_starts_with($name, self::ROLE_PREFIX)) {
            return substr($name, strlen(self::ROLE_PREFIX));
        }

        return null;
    }
}
