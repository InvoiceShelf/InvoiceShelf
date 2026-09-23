<?php

namespace App\Platform\Mcp\Tools;

use App\Platform\Mcp\McpContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Base for tools that change data.
 *
 * They are listed only on connections that may write, a connection may make
 * WRITES_PER_MINUTE changes a minute, and a tool that creates a record can be
 * given an `idempotency_key`: a second call with the same key within
 * IDEMPOTENCY_MINUTES returns the first call's result instead of creating
 * the record again, so a client that retries after a timeout does not
 * double-book.
 *
 * What goes wrong in `write()` is thrown as a ValidationException, which
 * reaches the client as a tool error it can read and act on.
 */
abstract class McpWriteTool extends McpTool
{
    public const WRITES_PER_MINUTE = 30;

    public const IDEMPOTENCY_MINUTES = 15;

    protected function writes(): bool
    {
        return true;
    }

    /**
     * Whether the tool takes an idempotency_key; true for tools that create.
     */
    protected function creates(): bool
    {
        return false;
    }

    /**
     * The change itself, returning what the tool answers with.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    abstract protected function write(Request $request, McpContext $context): array;

    public function handle(Request $request, McpContext $context): Response|ResponseFactory
    {
        $limiter = 'mcp-writes:'.$context->connection->id;

        if (RateLimiter::tooManyAttempts($limiter, self::WRITES_PER_MINUTE)) {
            return Response::error('This connection has made '.self::WRITES_PER_MINUTE.' changes in the last minute. Wait '.RateLimiter::availableIn($limiter).' seconds and try again.');
        }

        RateLimiter::hit($limiter, 60);

        $key = $request->get('idempotency_key');

        if (! $this->creates() || ! is_string($key) || trim($key) === '') {
            return Response::structured($this->write($request, $context));
        }

        $cacheKey = 'mcp:idempotency:'.$context->connection->id.':'.$this->name().':'.hash('sha256', $key);

        $result = Cache::lock($cacheKey.':lock', 30)->block(10, function () use ($cacheKey, $request, $context) {
            $stored = Cache::get($cacheKey);

            if (is_array($stored)) {
                return $stored + ['replayed' => true];
            }

            $result = $this->write($request, $context);
            Cache::put($cacheKey, $result, now()->addMinutes(self::IDEMPOTENCY_MINUTES));

            return $result;
        });

        return Response::structured($result);
    }

    /**
     * @return array<string, mixed>
     */
    protected function idempotencySchema(JsonSchema $schema): array
    {
        return [
            'idempotency_key' => $schema->string()->max(100)->description('Any unique string for this request. Calling again with the same key within '.self::IDEMPOTENCY_MINUTES.' minutes returns the first result instead of creating a second record; use it when retrying.'),
        ];
    }

    /**
     * Stop with a message the client can act on.
     *
     * @throws ValidationException
     */
    protected function refuse(string $message, string $field = 'request'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
