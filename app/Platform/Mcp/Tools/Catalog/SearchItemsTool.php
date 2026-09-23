<?php

namespace App\Platform\Mcp\Tools\Catalog;

use App\Domains\Catalog\Models\Item;
use App\Platform\Mcp\McpContext;
use App\Platform\Mcp\Presenters\ItemPresenter;
use App\Platform\Mcp\Tools\Concerns\Paginates;
use App\Platform\Mcp\Tools\McpTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search_items')]
#[Title('Search items')]
#[Description('Find catalogue items (products and services) by name or description. Returns their ids, prices, units and default taxes; an invoice line can name an item_id to take these from it.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class SearchItemsTool extends McpTool
{
    use Paginates;

    protected function ability(McpContext $context): ?array
    {
        return ['viewAny', Item::class];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Words to look for in the name or description. Leave out to list every item.'),
            ...$this->pageSchema($schema),
        ];
    }

    public function handle(Request $request, McpContext $context): ResponseFactory
    {
        $query = Item::query()->with(['unit', 'taxes'])->where('company_id', $context->company->id)->orderBy('name')->orderBy('id');

        foreach (preg_split('/\s+/', trim((string) $request->get('query')), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $needle = '%'.$term.'%';
            $query->where(fn ($where) => $where->where('name', 'LIKE', $needle)->orWhere('description', 'LIKE', $needle));
        }

        $page = $this->page($query, $request, fn (Item $item) => ItemPresenter::summary($item));

        return Response::structured(['items' => $page['rows'], 'page' => $page['page'], 'has_more' => $page['has_more']]);
    }
}
