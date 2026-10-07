<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Hand-made login for the salon's admin panel. There is deliberately no
 * registration: accounts are created from the server console with
 * `php artisan admin:create-user`.
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS_PER_MINUTE = 5;

    /**
     * Extra cap per connection, so rotating emails does not bypass the
     * per-email limit.
     */
    private const MAX_ATTEMPTS_PER_MINUTE_PER_IP = 20;

    /**
     * Bcrypt hash checked when the email does not exist, so a wrong email
     * takes as long as a wrong password and accounts cannot be enumerated
     * by response time.
     */
    private const DUMMY_HASH = '$2y$12$Y.UYbaHZPfbKeJgHyCtcUOn71FiX2enf4OKRV9kbZoOWjC0LQ5jre';

    public function create(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email'])).'|'.$request->ip();
        $ipThrottleKey = 'admin-login-ip|'.$request->ip();

        foreach ([$throttleKey => self::MAX_ATTEMPTS_PER_MINUTE, $ipThrottleKey => self::MAX_ATTEMPTS_PER_MINUTE_PER_IP] as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                throw ValidationException::withMessages([
                    'email' => 'Demasiados intentos. Vuelve a probar en '.RateLimiter::availableIn($key).' segundos.',
                ]);
            }
        }

        // No "remember me": sessions end with the browser or after the
        // configured session lifetime, since the panel holds personal data.
        $userExists = User::query()->where('email', $credentials['email'])->exists();

        if (! $userExists) {
            Hash::check($credentials['password'], self::DUMMY_HASH);
        }

        if (! $userExists || ! Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey, 60);
            RateLimiter::hit($ipThrottleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Email o contraseña incorrectos.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
