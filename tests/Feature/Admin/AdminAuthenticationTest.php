<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('guests are redirected to the admin login screen', function () {
    $this->get('/admin')->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Email')
        ->assertSee('Contraseña');
});

test('a salon user can log in with valid credentials and lands on the admin', function () {
    $user = User::factory()->create(['email' => 'salon@example.test']);

    $this->post(route('login'), [
        'email' => 'salon@example.test',
        'password' => 'password',
    ])->assertRedirect(route('admin.home'));

    $this->assertAuthenticatedAs($user);
});

test('wrong credentials show a generic message and do not log in', function (string $email, string $password) {
    User::factory()->create(['email' => 'salon@example.test']);

    $this->from(route('login'))
        ->post(route('login'), ['email' => $email, 'password' => $password])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Email o contraseña incorrectos.']);

    $this->assertGuest();
})->with([
    'wrong password' => ['salon@example.test', 'not-the-password'],
    'unknown email' => ['nobody@example.test', 'password'],
]);

test('login is throttled after five failed attempts in a minute', function () {
    User::factory()->create(['email' => 'salon@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => 'salon@example.test', 'password' => 'wrong']);
    }

    $response = $this->post(route('login'), ['email' => 'salon@example.test', 'password' => 'password']);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith('Demasiados intentos. Vuelve a probar en');
    $this->assertGuest();
});

test('there is no public registration screen', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/admin/register')->assertNotFound();
    expect(Route::has('register'))->toBeFalse();
});

test('a logged in user can log out and the admin asks for access again', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get('/admin')->assertRedirect(route('login'));
});

test('the login screen is not indexable', function () {
    $this->get(route('login'))->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('login is also throttled per connection when the email keeps changing', function () {
    User::factory()->create(['email' => 'salon@example.test']);

    foreach (range(1, 20) as $attempt) {
        $this->post(route('login'), ['email' => "guess{$attempt}@example.test", 'password' => 'wrong']);
    }

    $this->post(route('login'), ['email' => 'salon@example.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('an unknown email still costs a password hash check, so emails cannot be enumerated by timing', function () {
    Hash::spy();

    $this->post(route('login'), ['email' => 'nobody@example.test', 'password' => 'whatever']);

    Hash::shouldHaveReceived('check')->once();
    $this->assertGuest();
});

test('the login offers no long-lived remember-me session', function () {
    $this->get(route('login'))->assertDontSee('name="remember"', false);
});
