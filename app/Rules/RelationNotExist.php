<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RelationNotExist implements ValidationRule
{
    public $class;

    public $relation;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct(?string $class = null, ?string $relation = null)
    {
        $this->class = $class;
        $this->relation = $relation;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $relation = $this->relation;

        if ($this->class::find($value)->$relation()->exists()) {
            $fail("Relation {$this->relation} exists.");
        }

    }
}
