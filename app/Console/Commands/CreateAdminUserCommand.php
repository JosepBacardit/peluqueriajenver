<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * The only way to create (or reset the password of) an admin panel
 * account. The repository is public, so accounts are never seeded and the
 * password is always typed interactively, never passed as an argument
 * (it would end up in the shell history).
 */
#[Signature('admin:create-user')]
#[Description('Create an admin panel account, or change the password of an existing one')]
class CreateAdminUserCommand extends Command
{
    public function handle(): int
    {
        $name = trim((string) $this->ask('Name'));
        $email = strtolower(trim((string) $this->ask('Email')));

        if ($name === '' || Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('A name and a valid email are required.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user !== null && ! $this->confirm('An account with this email already exists. Change its password?')) {
            $this->info('Nothing changed.');

            return self::SUCCESS;
        }

        $password = (string) $this->secret('Password (at least '.User::MIN_PASSWORD_LENGTH.' characters)');
        $confirmation = (string) $this->secret('Repeat the password');

        if (mb_strlen($password) < User::MIN_PASSWORD_LENGTH) {
            $this->error('The password must be at least '.User::MIN_PASSWORD_LENGTH.' characters long.');

            return self::FAILURE;
        }

        if ($password !== $confirmation) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        if ($user !== null) {
            $user->update(['password' => $password]);
            $this->info("Password updated for {$email}.");

            return self::SUCCESS;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $this->info("Admin account created for {$email}.");

        return self::SUCCESS;
    }
}
