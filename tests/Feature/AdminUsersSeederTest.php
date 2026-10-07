<?php

use App\Models\User;
use Database\Seeders\AdminUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('it creates both accounts with the requested password', function () {
    (new AdminUsersSeeder)->run();

    expect(User::count())->toBe(2);

    foreach (['peluqueriajenver@gmail.com', 'josep@cobaprojects.com'] as $email) {
        $user = User::where('email', $email)->first();
        expect($user)->not->toBeNull();
        expect(Hash::check('password', $user->password))->toBeTrue();
    }
});

test('it is idempotent: running it twice does not duplicate the accounts', function () {
    (new AdminUsersSeeder)->run();
    (new AdminUsersSeeder)->run();

    expect(User::count())->toBe(2);
});

test('it never runs as part of the normal database seeder', function () {
    $this->seed();

    expect(User::count())->toBe(0);
});
