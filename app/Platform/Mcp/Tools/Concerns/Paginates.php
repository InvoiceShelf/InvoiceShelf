<?php

namespace App\Platform\Mcp\Tools\Concerns;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;

/**
 * Pages through a list: `limit` rows (20 unless asked, at most 100) of page
 * `page`, with `has_more` saying whether another page follows.
 */
trait Paginates
{
    /**
     * @return array<string, mixed>
     */
    protected function pageSchema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(100)->description('Rows per page, 20 unless given, at most 100.'),
            'page' => $schema->integer()->min(1)->description('The page to return, counting from 1.'),
        ];
    }

    /**
     * @param  Closure(mixed): array<string, mixed>  $present
     * @return array{rows: list<array<string, mixed>>, page: int, has_more: bool}
     */
    protected function page(Builder $query, Request $request, Closure $present): array
    {
        $limit = max(1, min(100, (int) ($request->get('limit') ?? 20)));
        $page = max(1, (int) ($request->get('page') ?? 1));

        $rows = $query->forPage($page, $limit + 1)->get();

        return [
            'rows' => $rows->take($limit)->map($present)->values()->all(),
            'page' => $page,
            'has_more' => $rows->count() > $limit,
        ];
    }
}
