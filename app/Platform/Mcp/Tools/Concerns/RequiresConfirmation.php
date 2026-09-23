<?php

namespace App\Platform\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

/**
 * Tools that delete records or send email do nothing unless `confirm` is
 * true, so a client acts on them only after asking its user.
 */
trait RequiresConfirmation
{
    /**
     * @return array<string, mixed>
     */
    protected function confirmSchema(JsonSchema $schema, string $consequence): array
    {
        return [
            'confirm' => $schema->boolean()->description("Must be true, and only after the user has agreed: {$consequence}. With false nothing happens.")->required(),
        ];
    }

    /**
     * The answer for a call that was not confirmed, or null when it was.
     *
     * @return array{aborted: true, message: string}|null
     */
    protected function unconfirmed(Request $request, string $consequence): ?array
    {
        if ($request->get('confirm') === true) {
            return null;
        }

        return [
            'aborted' => true,
            'message' => "Nothing was done. {$consequence}; ask the user, then call again with confirm set to true.",
        ];
    }
}
