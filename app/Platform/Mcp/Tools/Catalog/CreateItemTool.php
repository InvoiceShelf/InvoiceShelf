<?php

namespace App\Platform\Mcp\Tools\Catalog;

use App\Domains\Catalog\Application\ItemService;
use App\Domains\Catalog\Http\Requests\ItemsRequest;
use App\Domains\Catalog\Models\Item;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\ItemPresenter;
use App\Platform\Mcp\Support\DomainRequestValidator;
use App\Platform\Mcp\Tools\Catalog\Concerns\DescribesItems;
use App\Platform\Mcp\Tools\McpWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('create_item')]
#[Title('Create a catalogue item')]
#[Description('Add a product or service to the catalogue, so invoice lines can name it by item_id. Search first (search_items) so it is not added twice.')]
#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class CreateItemTool extends McpWriteTool
{
    use DescribesItems;

    public function __construct(
        private readonly DomainRequestValidator $validator,
        private readonly ItemService $items,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['create', Item::class];
    }

    protected function creates(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [...$this->itemSchema($schema, creating: true), ...$this->idempotencySchema($schema)];
    }

    protected function write(Request $request, McpContext $context): array
    {
        [$input, $taxes] = $this->itemInput($request->all(), $context->company->id);
        $validated = $this->validator->validate(ItemsRequest::class, $input, $context);

        $item = $this->items->create(
            $validated->getItemPayload(),
            $taxes ?? [],
            $context->company->id,
            $context->user->id,
            $validated->input('customFields'),
        );

        return ItemPresenter::summary($item->load(['unit', 'taxes']));
    }
}
