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
