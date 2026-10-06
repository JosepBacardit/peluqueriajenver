<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Mi cuenta": a user changing their own password, never someone else's
 * (there is no field to pick a different user — see AGENTS.md for why).
 */
class UpdatePasswordRequest extends FormRequest
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
            // "current_password": Laravel's own rule, checked against the
            // authenticated guard's own password — never a hand-rolled
            // Hash::check() that could silently check the wrong field.
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:'.User::MIN_PASSWORD_LENGTH, 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.min' => 'La contraseña nueva debe tener al menos '.User::MIN_PASSWORD_LENGTH.' caracteres.',
            'password.confirmed' => 'Las contraseñas nuevas no coinciden.',
        ];
    }
}
