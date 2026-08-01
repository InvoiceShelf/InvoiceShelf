<?php

namespace App\Rules;

use App\Models\Invoice;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An invoice may not be deleted while a credit note still reverses it.
 *
 * RelationNotExist cannot express this: the credit note is allowed to go, as
 * long as it goes in the same batch. Deleting only the original would leave the
 * credit note pointing at a row that no longer exists, so its detail page, its
 * PDF banner and the delete-side balance restore all lose their counterpart.
 * The rule therefore looks at the whole `ids` list, not just the value it was
 * handed.
 */
class CreditNoteDeletedTogether implements ValidationRule
{
    /**
     * @param  array<int, mixed>  $ids  every id in the delete request
     */
    public function __construct(private readonly array $ids) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $batch = array_map('intval', array_filter($this->ids, 'is_numeric'));

        $orphaned = Invoice::where('related_invoice_id', (int) $value)
            ->where('type', Invoice::TYPE_CREDIT_NOTE)
            ->whereNotIn('id', $batch)
            ->exists();

        if ($orphaned) {
            $fail('Credit note exists.');
        }
    }
}
