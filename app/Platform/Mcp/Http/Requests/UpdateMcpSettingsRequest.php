<?php

namespace App\Platform\Mcp\Http\Requests;

use App\Platform\Mcp\OAuth\RedirectPolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The administrator's MCP settings: the switch, and extra redirect origins.
 */
class UpdateMcpSettingsRequest extends FormRequest
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
            'enabled' => ['sometimes', 'boolean'],
            'redirect_domains' => ['sometimes', 'array', 'max:50'],
            'redirect_domains.*' => ['string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! RedirectPolicy::isValidOrigin(rtrim($value, '/'))) {
                    $fail('Each redirect domain must be an https origin such as https://agent.example.com, with no path or wildcard.');
                }
            }],
        ];
    }
}
