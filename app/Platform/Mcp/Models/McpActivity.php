<?php

namespace App\Platform\Mcp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change or an email an MCP connection made: the tool it called and the id
 * of the record it wrote, kept for the company to look back on.
 */
class McpActivity extends Model
{
    protected $table = 'mcp_activity';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mcp_connection_id' => 'integer',
            'user_id' => 'integer',
            'company_id' => 'integer',
            'subject_id' => 'integer',
        ];
    }

    public function mcpConnection(): BelongsTo
    {
        return $this->belongsTo(McpConnection::class, 'mcp_connection_id');
    }
}
