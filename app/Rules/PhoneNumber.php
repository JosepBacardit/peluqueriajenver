<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 9 to 15 digits, allowing spaces, hyphens, parentheses and a leading "+".
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? trim($value) : '';
        $digits = strlen((string) preg_replace('/\D/', '', $value));

        if (! preg_match('/^\+?[0-9\s\-()]+$/', $value) || $digits < 9 || $digits > 15) {
            $fail('Escribe un teléfono válido (de 9 a 15 dígitos).');
        }
    }
}
