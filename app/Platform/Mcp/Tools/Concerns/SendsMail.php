<?php

namespace App\Platform\Mcp\Tools\Concerns;

use App\Platform\Mcp\McpContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

/**
 * Email sent on a connection's behalf: at most SENDS_PER_HOUR an hour for one
 * connection and SENDS_PER_DAY a day for one company, whatever connections it
 * has. The message starts from what the app's send dialog would prefill.
 */
trait SendsMail
{
    public const SENDS_PER_HOUR = 20;

    public const SENDS_PER_DAY = 100;

    /**
     * @return array<string, mixed>
     */
    protected function mailSchema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->string()->description('Recipient; the customer\'s email when left out.'),
            'cc' => $schema->string(),
            'bcc' => $schema->string(),
            'subject' => $schema->string()->description('The company\'s usual subject when left out.'),
            'body' => $schema->string()->description('The company\'s usual message when left out; placeholders like {INVOICE_NUMBER} are filled in.'),
        ];
    }

    /**
     * The message: the dialog's defaults with whatever was given on top.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    protected function message(Request $request, array $defaults): array
    {
        foreach (['to', 'cc', 'bcc', 'subject', 'body'] as $field) {
            if (is_string($request->get($field)) && trim($request->get($field)) !== '') {
                $defaults[$field] = $request->get($field);
            }
        }

        return $defaults;
    }

    /**
     * Count one email against the limits, or refuse it when either is used up.
     *
     * @throws ValidationException
     */
    protected function spendSendAllowance(McpContext $context): void
    {
        $connection = 'mcp-sends:connection:'.$context->connection->id;
        $company = 'mcp-sends:company:'.$context->company->id;

        if (RateLimiter::tooManyAttempts($connection, self::SENDS_PER_HOUR)) {
            throw ValidationException::withMessages(['request' => 'This connection has sent '.self::SENDS_PER_HOUR.' emails in the last hour. Try again later.']);
        }

        if (RateLimiter::tooManyAttempts($company, self::SENDS_PER_DAY)) {
            throw ValidationException::withMessages(['request' => 'Connected apps have sent '.self::SENDS_PER_DAY.' emails for this company today. Try again tomorrow, or send from the app.']);
        }

        RateLimiter::hit($connection, 3600);
        RateLimiter::hit($company, 86400);
    }
}
