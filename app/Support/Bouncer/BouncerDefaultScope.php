<?php

namespace App\Support\Bouncer;

use Silber\Bouncer\Database\Scope\Scope;

class BouncerDefaultScope extends Scope
{
    /**
     * A direct company assignment replaces global role assignments for that user
     * in the active company unless that membership explicitly opts into combining
     * both sets; restricted-company denial is handled before Bouncer is scoped.
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

    public function applyToRelationQuery($query, $table)
    {
        if (is_null($this->scope)) {
            return $query;
        }

        if ($table === 'assigned_roles') {
            return $query->where(function ($query) use ($table) {
                $query->where("{$table}.scope", $this->scope)
                    ->orWhere(function ($query) use ($table) {
                        $query->whereNull("{$table}.scope")
                            ->where(function ($global) use ($table) {
                                $global->whereNotExists(function ($direct) use ($table) {
                                    $direct->selectRaw('1')
                                        ->from("{$table} as direct_assignments")
                                        ->whereColumn('direct_assignments.entity_id', "{$table}.entity_id")
                                        ->whereColumn('direct_assignments.entity_type', "{$table}.entity_type")
                                        ->where('direct_assignments.scope', $this->scope);
                                })->orWhereExists(function ($membership) use ($table) {
                                    $membership->selectRaw('1')
                                        ->from('user_company')
                                        ->whereColumn('user_company.user_id', "{$table}.entity_id")
                                        ->where("{$table}.entity_type", 'user')
                                        ->where('user_company.company_id', $this->scope)
                                        ->where('user_company.include_global_roles', true);
                                });
                            });
                    });
            });
        }

        return $this->applyToQuery($query, $table);
    }

    protected function applyToQuery($query, $table)
    {
        return $query->where(function ($query) use ($table) {
            $query->where("{$table}.scope", $this->scope)
                ->orWhereNull("{$table}.scope");
        });
    }
}
