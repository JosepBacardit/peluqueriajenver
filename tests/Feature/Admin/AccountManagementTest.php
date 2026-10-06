<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'current-password-123']);
});

test('guests are redirected to login', function () {
    $this->get(route('admin.account.edit'))->assertRedirect(route('login'));
});

test('the account page shows the signed-in user\'s name and email', function () {
    $this->actingAs($this->user)
        ->get(route('admin.account.edit'))
        ->assertOk()
        ->assertSee($this->user->name)
        ->assertSee($this->user->email);
});

test('a user can change their own password', function () {
    $this->actingAs($this->user)
        ->put(route('admin.account.update-password'), [
            'current_password' => 'current-password-123',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertRedirect(route('admin.account.edit'))
        ->assertSessionHas('status', 'Contraseña actualizada.');

    expect(Hash::check('a-brand-new-password', $this->user->fresh()->password))->toBeTrue();
});

test('the wrong current password is rejected and nothing changes', function () {
    $originalHash = $this->user->password;

    $this->actingAs($this->user)
        ->from(route('admin.account.edit'))
        ->put(route('admin.account.update-password'), [
            'current_password' => 'not-the-right-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertRedirect(route('admin.account.edit'))
        ->assertSessionHasErrors('current_password');

    expect($this->user->fresh()->password)->toBe($originalHash);
});

/**
 * Review finding L1 (.ai/reviews/seeders-account.md, 2026-10-06): the
 * same aria-invalid/aria-describedby pattern the rest of the panel
 * already uses, so a screen reader announces the failed field and reads
 * its error.
 */
test('a field with an error is linked to its message with aria-describedby/aria-invalid', function () {
    $html = $this->actingAs($this->user)
        ->from(route('admin.account.edit'))
        ->put(route('admin.account.update-password'), [
            'current_password' => 'not-the-right-password',
            'password' => 'short',
            'password_confirmation' => 'something-else',
        ])
        ->assertRedirect(route('admin.account.edit'));

    $page = $this->get(route('admin.account.edit'))->getContent();

    expect($page)->toContain('aria-invalid="true" aria-describedby="current_password-error"');
    expect($page)->toContain('id="current_password-error"');
    expect($page)->toContain('aria-invalid="true" aria-describedby="password-error"');
    expect($page)->toContain('id="password-error"');
});

test('a new password shorter than the minimum is rejected', function () {
    $this->actingAs($this->user)
        ->put(route('admin.account.update-password'), [
            'current_password' => 'current-password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('current-password-123', $this->user->fresh()->password))->toBeTrue();
});

test('a mismatched confirmation is rejected', function () {
    $this->actingAs($this->user)
        ->put(route('admin.account.update-password'), [
            'current_password' => 'current-password-123',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'something-else-entirely',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('current-password-123', $this->user->fresh()->password))->toBeTrue();
});

test('too many attempts in a short time are throttled, by user, with its own message', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($this->user)
            ->put(route('admin.account.update-password'), [
                'current_password' => 'wrong-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertSessionHasErrors('current_password');
    }

    $this->actingAs($this->user)
        ->put(route('admin.account.update-password'), [
            'current_password' => 'wrong-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect(session('errors')->first('current_password'))->toContain('Demasiados intentos');
});

/**
 * auth.session (the admin route group's own middleware) compares, on
 * every request, the password hash a session has cached against the
 * user's current one; a session still holding the hash from before the
 * change gets signed out on its own next request — without the person
 * who just changed it (whose own session re-syncs right after the
 * response) ever being logged out by their own change.
 */
test('changing the password signs out a session still holding the old hash, on its own next request', function () {
    $oldHash = $this->user->password;

    $this->user->update(['password' => 'a-brand-new-password']);
    Auth::logoutOtherDevices('a-brand-new-password');

    $this->withSession(['password_hash_web' => $oldHash])
        ->actingAs($this->user)
        ->get(route('admin.agenda'))
        ->assertRedirect(route('login'));
});

test('the current session is not logged out by its own password change', function () {
    $this->actingAs($this->user)
        ->put(route('admin.account.update-password'), [
            'current_password' => 'current-password-123',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])
        ->assertRedirect(route('admin.account.edit'));

    // Same test session, right after changing its own password: still in.
    $this->get(route('admin.agenda'))->assertOk();
});

/**
 * Review finding M2 (.ai/reviews/seeders-account.md, 2026-10-06): a
 * password change is a change of credentials, same as logging in
 * (LoginController::store() already regenerates there) — the session id
 * itself must change, not just the password hash other sessions compare
 * against, so an id exposed before the change (fixation, a leaked
 * cookie, a shared device) stops being valid.
 */
test('the session id changes after a successful password change', function () {
    $this->actingAs($this->user)->get(route('admin.account.edit'));
    $idBefore = session()->getId();

    $this->put(route('admin.account.update-password'), [
        'current_password' => 'current-password-123',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect(route('admin.account.edit'));

    expect(session()->getId())->not->toBe($idBefore);
    // Still the same, now re-authenticated, session: no bounce to login.
    $this->get(route('admin.agenda'))->assertOk();
});

/**
 * Review finding L2: only a wrong current password should spend down
 * "password-change"'s budget, the same rule LoginController already
 * applies to its own limiter — a correct change must not count against
 * it, however many times it happens in the same window. (A naive
 * RateLimiter::clear() call from the controller, against the generic
 * throttle: middleware, could not actually achieve this: the middleware
 * hashes its own cache key together with the limiter's name, so the
 * throttling was moved entirely into UpdatePasswordRequest instead,
 * where hit/clear use the exact same key.)
 */
test('successful password changes do not count against the rate limit', function () {
    // One real session throughout, the same way a browser would submit
    // the form several times — not re-authenticating by hand each time
    // (auth.session already re-syncs this same session after every
    // response; see "the current session is not logged out...").
    $this->actingAs($this->user);

    for ($i = 0; $i < 7; $i++) {
        $current = $i % 2 === 0 ? 'current-password-123' : 'a-brand-new-password';
        $new = $i % 2 === 0 ? 'a-brand-new-password' : 'current-password-123';

        $this->put(route('admin.account.update-password'), [
            'current_password' => $current,
            'password' => $new,
            'password_confirmation' => $new,
        ])
            ->assertRedirect(route('admin.account.edit'))
            ->assertSessionHas('status', 'Contraseña actualizada.');
    }
});

test('a successful change resets the limit, so earlier failed attempts are forgotten', function () {
    $this->actingAs($this->user);

    for ($i = 0; $i < 4; $i++) {
        $this->put(route('admin.account.update-password'), [
            'current_password' => 'wrong-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertSessionHasErrors('current_password');
    }

    // The 5th attempt, correct this time: clears the limiter.
    $this->put(route('admin.account.update-password'), [
        'current_password' => 'current-password-123',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect(route('admin.account.edit'));

    // 4 more wrong attempts: without the reset, this would be the 9th in
    // the window (over the limit of 5) and get throttled instead of
    // still reporting the wrong-password message.
    for ($i = 0; $i < 4; $i++) {
        $this->put(route('admin.account.update-password'), [
            'current_password' => 'still-wrong',
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertSessionHasErrors('current_password');
    }

    expect(session('errors')->first('current_password'))
        ->toBe('La contraseña actual no es correcta.');
});
