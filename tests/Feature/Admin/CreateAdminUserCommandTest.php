<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('it creates an admin account asking for the password twice', function () {
    $this->artisan('admin:create-user')
        ->expectsQuestion('Name', 'Salon Owner')
        ->expectsQuestion('Email', 'owner@example.test')
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-password')
        ->expectsQuestion('Repeat the password', 'a-long-password')
        ->expectsOutputToContain('created')
        ->assertExitCode(0);

    $user = User::where('email', 'owner@example.test')->firstOrFail();
    expect($user->name)->toBe('Salon Owner');
    expect(Hash::check('a-long-password', $user->password))->toBeTrue();
});

test('it rejects a password shorter than twelve characters', function () {
    $this->artisan('admin:create-user')
        ->expectsQuestion('Name', 'Salon Owner')
        ->expectsQuestion('Email', 'owner@example.test')
        ->expectsQuestion('Password (at least 12 characters)', 'short-pass1')
        ->expectsQuestion('Repeat the password', 'short-pass1')
        ->expectsOutputToContain('at least 12 characters')
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
});

test('it rejects passwords that do not match', function () {
    $this->artisan('admin:create-user')
        ->expectsQuestion('Name', 'Salon Owner')
        ->expectsQuestion('Email', 'owner@example.test')
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-password')
        ->expectsQuestion('Repeat the password', 'another-password')
        ->expectsOutputToContain('do not match')
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
});

test('it rejects an invalid email', function () {
    $this->artisan('admin:create-user')
        ->expectsQuestion('Name', 'Salon Owner')
        ->expectsQuestion('Email', 'not-an-email')
        ->expectsOutputToContain('valid email')
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
});

test('for an existing email it changes the password only when confirmed', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);

    $this->artisan('admin:create-user')
        ->expectsQuestion('Name', 'Salon Owner')
        ->expectsQuestion('Email', 'owner@example.test')
        ->expectsConfirmation('An account with this email already exists. Change its password?', 'yes')
        ->expectsQuestion('Password (at least 12 characters)', 'a-brand-new-password')
        ->expectsQuestion('Repeat the password', 'a-brand-new-password')
        ->expectsOutputToContain('updated')
        ->assertExitCode(0);

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();
    expect(User::count())->toBe(1);
});

test('for an existing email it changes nothing when the change is declined', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);
    $originalHash = $user->password;

    $this->artisan('admin:create-user')
        ->expectsQuestion('Name', 'Salon Owner')
        ->expectsQuestion('Email', 'owner@example.test')
        ->expectsConfirmation('An account with this email already exists. Change its password?', 'no')
        ->expectsOutputToContain('Nothing changed')
        ->assertExitCode(0);

    expect($user->fresh()->password)->toBe($originalHash);
});

test('the database seeder never creates admin accounts', function () {
    $this->seed();

    expect(User::count())->toBe(0);
});
