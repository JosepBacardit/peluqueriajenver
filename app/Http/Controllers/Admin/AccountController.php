<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Mi cuenta": today, just the password. No field to change another
 * user's (every account has the same permissions; see AGENTS.md for how
 * a forgotten password is recovered instead).
 */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('admin.account.edit');
    }

    /**
     * `UpdatePasswordRequest` already confirmed `current_password` against
     * the guard's own password. `auth.session` (on this route's group)
     * is what actually makes `logoutOtherDevices()` log the *other*
     * sessions out on their own next request — it compares the password
     * hash each one has cached against the user's current one and signs
     * that session out the moment they stop matching; this request's own
     * session re-syncs itself right after the response, so the person who
     * just changed it is never logged out by their own change.
     *
     * `session()->regenerate()` (review finding M2, .ai/reviews/seeders-account.md):
     * a changed password is a change of credentials, same as logging in
     * (`LoginController::store()` already regenerates there) — without
     * this, a session id exposed before the change (fixation, a leaked
     * cookie, a shared device) would stay just as valid afterwards, since
     * `logoutOtherDevices()` only acts on sessions still holding the
     * *old* password hash, not on the id itself.
     *
     * The "only a wrong current password counts against the rate limit"
     * rule (review finding L2) lives entirely in `UpdatePasswordRequest`
     * now (`prepareForValidation()`/`after()`/`passedValidation()`), not
     * here: by the time this method runs, validation already passed, so
     * there is nothing left to hit or clear.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $password = $request->validated('password');

        $request->user()->update(['password' => $password]);

        Auth::logoutOtherDevices($password);
        $request->session()->regenerate();

        return redirect()->route('admin.account.edit')->with('status', 'Contraseña actualizada.');
    }
}
