<?php

namespace App\Bouncer\Scopes;

use Silber\Bouncer\Database\Scope\Scope;

class DefaultScope extends Scope
{
    /**
     * Scope the given model query to the current tenant.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string|null $table
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyToModelQuery($query, $table = null)
    {
        if (is_null($this->scope) || $this->onlyScopeRelations) {
            return $query;
        }

        if (is_null($table)) {
            $table = $query->getModel()->getTable();
        }

        return $this->applyToQuery($query, $table);
    }

    /**
     * Scope the given relationship query to the current tenant.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string $table
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function applyToRelationQuery($query, $table)
    {
        if (is_null($this->scope)) {
            return $query;
        }

        return $this->applyToQuery($query, $table);
    }

    /**
     * Apply the current scope to the given query.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param string $table
     *
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected function applyToQuery($query, $table)
    {
        return $query->where(function ($query) use ($table) {
            $query->where("{$table}.scope", $this->scope)
                ->orWhereNull("{$table}.scope");
        });
    }
}
