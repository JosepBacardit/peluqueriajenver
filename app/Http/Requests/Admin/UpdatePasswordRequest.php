<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * "Mi cuenta": a user changing their own password, never someone else's
 * (there is no field to pick a different user — see AGENTS.md for why).
 *
 * Throttled the same way LoginController throttles a login attempt
 * (review finding L2, .ai/reviews/seeders-account.md, 2026-10-06): only a
 * wrong current password spends down the budget, cleared the moment it
 * is entered correctly — not the generic `throttle:` route middleware,
 * which hits its counter on every request regardless of outcome and
 * hashes its own cache key together with the limiter's name
 * (ThrottleRequests::$shouldHashKeys), so a plain
 * RateLimiter::clear('password-change:'.$id) from the controller would
 * silently clear a key the middleware never actually used — proven by a
 * test that kept getting throttled on its 6th *successful* change before
 * this was reshaped to live entirely here, with one key this class fully
 * controls.
 */
class UpdatePasswordRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 600;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same upfront check as LoginController::store(): blocked before
     * even looking at the fields, so a blocked guess never gets to
     * consume anything else either.
     */
    protected function prepareForValidation(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'current_password' => 'Demasiados intentos. Espera unos minutos y vuelve a probar.',
            ]);
        }
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
     * Only a wrong current password counts against the limit — not a
     * weak/mismatched new one, same distinction LoginController draws
     * between a bad credential and anything else.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('current_password')) {
                RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
            }
        }];
    }

    /**
     * Validation passed outright (current_password included): forget
     * whatever this user had accumulated.
     */
    protected function passedValidation(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    private function throttleKey(): string
    {
        return 'password-change:'.$this->user()->id;
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
