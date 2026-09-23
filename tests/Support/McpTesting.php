<?php

namespace Tests\Support;

use App\Domains\Accounts\Models\User;
use App\Platform\Mcp\Application\McpSettings;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Models\McpConnection;
use App\Platform\Mcp\Servers\InvoiceShelfServer;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Laravel\Mcp\Server\Tool;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

/**
 * Helpers for tests that drive the MCP server the way a client does.
 */
final class McpTesting
{
    public const PROTOCOL = '2025-06-18';

    /**
     * Switch the server on with in-memory signing keys.
     */
    public static function enable(): void
    {
        OAuthTesting::useKeys();
        app(McpSettings::class)->setEnabled(true);
    }

    /**
     * Register a client the way an AI client does, returning it.
     */
    public static function register(TestCase $test, string $redirectUri = OAuthTesting::REDIRECT_URI, string $name = 'Claude'): Client
    {
        $response = $test->postJson('/oauth/register', [
            'client_name' => $name,
            'redirect_uris' => [$redirectUri],
        ])->assertCreated();

        return app(ClientRepository::class)->find($response->json('client_id'));
    }

    /**
     * Run registration, consent and the code exchange for a user, binding the
     * connection to a company with an access level.
     *
     * @return array{tokens: array<string, mixed>, client: Client}
     */
    public static function connect(TestCase $test, User $user, int $companyId, string $access = McpConnection::ACCESS_WRITE, ?Client $client = null): array
    {
        $client ??= self::register($test, 'https://claude.ai/api/mcp/auth_callback');

        $tokens = OAuthTesting::authorize($test, $user, $client, 'mcp:use', [
            'company_id' => $companyId,
            'access' => $access,
        ], 'https://claude.ai/api/mcp/auth_callback');

        return ['tokens' => $tokens, 'client' => $client];
    }

    /**
     * Send one JSON-RPC request to /mcp with a bearer token.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $headers
     */
    public static function rpc(TestCase $test, string $token, string $method, array $params = [], array $headers = []): TestResponse
    {
        app('auth')->forgetGuards();

        return $test->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => self::PROTOCOL,
            ...$headers,
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params === [] ? (object) [] : $params,
        ]);
    }

    /**
     * Bind the context a tool reads, for calling a tool directly through the
     * server's testing helpers rather than over HTTP.
     */
    public static function actAs(User $user, int $companyId, string $access = McpConnection::ACCESS_WRITE): McpContext
    {
        $connection = McpConnection::query()->create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'oauth_client_id' => (string) Str::uuid(),
            'access' => $access,
            'client_name' => 'Test client',
        ]);

        // The host's policies ask Bouncer about the signed-in user, which over
        // HTTP is the token's owner.
        auth()->setUser($user);
        request()->headers->set('company', (string) $companyId);
        BouncerFacade::scope()->to($companyId);

        $context = new McpContext($connection, $user, $connection->company);
        app()->instance(McpContext::class, $context);

        return $context;
    }

    /**
     * Call a tool as the given connection and hand back what it returned,
     * failing the test when the tool answered with an error.
     *
     * @param  class-string<Tool>  $tool
     * @return array<string, mixed>
     */
    public static function call(User $user, string $tool, array $arguments = []): array
    {
        $content = null;

        InvoiceShelfServer::actingAs($user)
            ->tool($tool, $arguments)
            ->assertHasNoErrors()
            ->assertStructuredContent(function (AssertableJson $json) use (&$content) {
                $content = $json->toArray();
                $json->etc();
            });

        return $content;
    }
}
