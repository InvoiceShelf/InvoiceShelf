<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Rules\RelationNotExist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ids' => [
                'required',
            ],
            'ids.*' => [
                'required',
                Rule::exists('items', 'id'),
                new RelationNotExist(Item::class, 'invoiceItems'),
                new RelationNotExist(Item::class, 'estimateItems'),
                new RelationNotExist(Item::class, 'taxes'),
            ],
        ];
    }
}
