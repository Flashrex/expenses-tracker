<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('user:create')]
#[Description('Create the single application user')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->error('A user already exists. Only one account is allowed.');

            return self::FAILURE;
        }

        $email = text(
            label: 'Email',
            required: true,
            validate: ['email' => ['required', 'email']],
        );

        $password = password(
            label: 'Password',
            required: true,
            validate: ['password' => ['required', 'string', 'min:8']],
        );

        User::create(['email' => $email, 'password' => $password]);

        $this->components->info("User {$email} created.");

        return self::SUCCESS;
    }
}
