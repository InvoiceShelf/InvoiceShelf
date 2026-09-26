<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Accounts\Models\RolePreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload rules for defining or renaming a company role.
 *
 * Role names are unique per company rather than per install, so the uniqueness
 * check is narrowed by hand to the scope named in the `company` header. A
 * rename excuses the role from its own name, but only when the verb is PUT --
 * kept as is, an otherwise identical PATCH collides with the stored name.
 */
class RoleRequest extends FormRequest
{
    /**
     * Access is settled by the role policy in the controller, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * What a role submission has to satisfy before it is written.
     */
    public function rules(): array
    {
        $name = Rule::unique('roles')->where('scope', $this->header('company'));

        if ($this->getMethod() === 'PUT') {
            $name->ignore($this->route('role')->id, 'id');
        }

        return [
            'name' => ['required', 'string', $name, function (string $attribute, mixed $value, \Closure $fail): void {
                if (RolePreset::keyFromRoleName(strtolower((string) $value)) !== null) {
                    $fail('This name is reserved for the role presets.');
                }
            }],
            'abilities' => ['required'],
            'abilities.*' => ['required'],
        ];
    }

    /**
     * The name, stamped with the scope. Nothing else the caller sent is
     * written: the title follows the name.
     */
    public function getRolePayload()
    {
        return [
            'name' => $this->input('name'),
            'scope' => $this->header('company'),
        ];
    }
}
