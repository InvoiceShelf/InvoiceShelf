<?php

namespace App\Platform\Mcp\Tools\Sales;

use App\Domains\Accounts\Application\UserLocale;
use App\Domains\Sales\Application\Composition\EstimateMailDefaults;
use App\Domains\Sales\Application\EstimateService;
use App\Domains\Sales\Http\Requests\SendEstimatesRequest;
use App\Domains\Sales\Models\Estimate;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Support\DomainRequestValidator;
use App\Platform\Mcp\Tools\Concerns\RequiresConfirmation;
use App\Platform\Mcp\Tools\Concerns\SendsMail;
use App\Platform\Mcp\Tools\McpWriteTool;
use App\Platform\Mcp\Tools\Sales\Concerns\DescribesDocuments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('send_estimate')]
#[Title('Email an estimate')]
#[Description('Email an estimate to the customer with its PDF, the way the app\'s send dialog does, and mark a draft as sent. This reaches someone outside the app: ask the user first and set confirm to true.')]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(true)]
class SendEstimateTool extends McpWriteTool
{
    use DescribesDocuments;
    use RequiresConfirmation;
    use SendsMail;

    public function __construct(
        private readonly EstimateMailDefaults $defaults,
        private readonly DomainRequestValidator $validator,
        private readonly EstimateService $estimates,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['send-estimate', Estimate::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->documentReference($schema, 'estimate'),
            ...$this->mailSchema($schema),
            ...$this->confirmSchema($schema, 'the estimate is emailed to the customer'),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $estimate = $this->findEstimate($request, $context);
        $this->authorizeRecord($context, 'send estimate', $estimate);

        if ($aborted = $this->unconfirmed($request, "Sending emails estimate {$estimate->estimate_number} to the customer")) {
            return $aborted;
        }

        $message = $this->message($request, $this->defaults->for($estimate, UserLocale::for($context->user, $context->company->id)));
        $validated = $this->validator->validate(SendEstimatesRequest::class, $message, $context, 'POST', ['estimate' => $estimate]);

        $this->spendSendAllowance($context);
        $this->estimates->send($estimate, $validated->all());

        return ['id' => $estimate->id, 'number' => $estimate->estimate_number, 'sent_to' => $message['to'], 'status' => $estimate->fresh()->status];
    }
}
