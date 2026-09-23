<?php

namespace App\Platform\Mcp\Models;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An AI client a user connected to one company through OAuth consent.
 *
 * The company is chosen on the consent screen and never taken from the
 * client, so a model cannot be talked into acting in another company. The
 * access level caps what the client may do; the user's own role in the
 * company caps it further.
 */
class McpConnection extends Model
{
    public const ACCESS_READ = 'read';

    public const ACCESS_WRITE = 'write';

    protected $table = 'mcp_connections';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'company_id' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function canWrite(): bool
    {
        return $this->access === self::ACCESS_WRITE;
    }
}
