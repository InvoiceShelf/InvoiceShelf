<?php

namespace App\Domains\Accounts\Http\Requests;

use App\Domains\Metadata\Http\Requests\Concerns\ValidatesCustomFields;
use App\Rules\IdnEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Validates the signed-in account editing its own profile.
 *
 * A display name is the only thing genuinely demanded. The password field is
 * optional, so a form saved with it left alone keeps the hash already on file,
 * and the address has to stay unique installation-wide with the caller's own
 * row stepped over — otherwise re-saving an unchanged form would collide with
 * itself.
 */
class ProfileRequest extends FormRequest
{
    use ValidatesCustomFields;

    /**
     * Everyone signed in may edit their own profile; there is no target to
     * weigh up, so the gate is open.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required'],
            'password' => ['nullable', 'min:8'],
            'email' => [
                'required',
                new IdnEmail,
                Rule::unique('users')->ignore(Auth::id(), 'id'),
            ],
            ...$this->customFieldRules(),
        ];
    }
}
