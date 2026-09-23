<?php

namespace App\Platform\Mcp\Console;

use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;

/**
 * Daily clean-up. Anyone can register an OAuth client, so registrations that
 * never led to a connection are removed after a day, together with tokens
 * and codes that expired more than a week ago.
 */
class PruneMcp extends Command
{
    protected $signature = 'mcp:prune {--hours=24 : Age after which an unused client registration is removed}';

    protected $description = 'Remove unused MCP client registrations and expired OAuth tokens.';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));

        $removed = Passport::client()->newQuery()
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('id', McpConnection::query()->select('oauth_client_id'))
            ->whereNotIn('id', Passport::token()->newQuery()->where('revoked', false)->select('client_id'))
            ->delete();

        Artisan::call('passport:purge', ['--expired' => true]);

        $this->components->info("Removed {$removed} unused client registrations.");

        return self::SUCCESS;
    }
}
