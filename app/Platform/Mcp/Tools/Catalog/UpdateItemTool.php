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

#[Name('update_item')]
#[Title('Change a catalogue item')]
#[Description('Change a catalogue item. Only what is given changes. Documents already issued keep the prices they were issued with.')]
#[IsReadOnly(false)]
#[IsIdempotent(true)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class UpdateItemTool extends McpWriteTool
{
    use DescribesItems;

    public function __construct(
        private readonly DomainRequestValidator $validator,
        private readonly ItemService $items,
    ) {}

    protected function ability(McpContext $context): ?array
    {
        return ['edit-item', Item::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->integer()->description('The item, from search_items.')->required(),
            ...$this->itemSchema($schema, creating: false),
        ];
    }

    protected function write(Request $request, McpContext $context): array
    {
        $item = Item::query()->with('taxes')->where('company_id', $context->company->id)->find($request->get('item_id'));

        if (! $item) {
            $this->refuse('There is no item with this id in the company.', 'item_id');
        }

        $this->authorizeRecord($context, 'update', $item);

        [$input, $taxes] = $this->itemInput($request->all(), $context->company->id, $item);
        $validated = $this->validator->validate(ItemsRequest::class, $input, $context, 'PUT', ['item' => $item]);

        // Left out, the item keeps its taxes: the update replaces them with
        // whatever it is handed.
        $keep = $item->taxes->map(fn ($tax) => $tax->only(['tax_type_id', 'calculation_type', 'fixed_amount', 'amount', 'percent', 'name', 'compound_tax', 'collective_tax']))->all();

        $item = $this->items->update(
            $item,
            $validated->getItemPayload(),
            $taxes ?? $keep,
            $context->company->id,
            $validated->input('customFields'),
        );

        return ItemPresenter::summary($item->load(['unit', 'taxes']));
    }
}
