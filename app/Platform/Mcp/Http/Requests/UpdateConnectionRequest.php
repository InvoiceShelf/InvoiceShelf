<?php

namespace App\Platform\Mcp\Http\Requests;

use App\Platform\Mcp\Models\McpConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A connection can only be lowered to read only from here; more access, or
 * another company, takes a new consent from the client.
 */
class UpdateConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'access' => ['required', Rule::in([McpConnection::ACCESS_READ])],
        ];
    }
}
