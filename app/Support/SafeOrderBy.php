<?php

namespace App\Support;

class SafeOrderBy
{
    /**
     * Apply a safe ORDER BY clause.
     *
     * The orderByField / orderBy values originate from user-supplied query
     * parameters and were previously passed straight into Eloquent's orderBy(),
     * allowing arbitrary SQL expressions in the ORDER BY clause (boolean-based
     * blind injection). Only a plain, optionally table-qualified column
     * identifier is accepted as the sort target; anything containing SQL syntax
     * (parentheses, whitespace, sub-selects, ...) falls back to a safe default.
     * The direction is clamped to asc/desc. Column aliases such as a joined
     * "customers.name" remain valid, so legitimate sorts are unaffected.
     */
    public static function apply($query, $orderByField, $orderBy = 'desc', string $default = 'created_at')
    {
        $direction = strtolower((string) $orderBy) === 'asc' ? 'asc' : 'desc';

        $field = is_string($orderByField) ? $orderByField : '';

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $field)) {
            $field = $default;
        }

        return $query->orderBy($field, $direction);
    }
}
