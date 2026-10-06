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
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $password = $request->validated('password');

        $request->user()->update(['password' => $password]);

        Auth::logoutOtherDevices($password);

        return redirect()->route('admin.account.edit')->with('status', 'Contraseña actualizada.');
    }
}
