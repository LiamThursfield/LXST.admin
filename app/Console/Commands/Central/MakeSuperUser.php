<?php

namespace App\Console\Commands\Central;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('central:make:super-user')]
#[Description('Makes a super user for the Central App')]
class MakeSuperUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $firstName = $this->ask('What is your first name?');
        $lastName = $this->ask('What is your last name?');
        $email = $this->ask('What is your email?');

        $password = $this->secret('Create password');
        if (! $password || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return;
        }

        $passwordConfirmation = $this->secret('Confirm password');
        if ($password !== $passwordConfirmation) {
            $this->error('Passwords do not match.');

            return;
        }

        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // TODO: Add apply roles once we have them
        // $user->assignRole(Role::whereName(SUPER)->first());

        $this->comment('User Created: '.$user->name);
    }
}
