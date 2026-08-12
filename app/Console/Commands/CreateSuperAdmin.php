<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the platform administrator — the person who runs this system and
 * sells it to companies.
 *
 * A command rather than a seeder **because the repository is public**. A
 * committed password is a published one, and this account can open, suspend
 * and inspect every company on the platform.
 *
 * Existing accounts are promoted rather than duplicated, so re-running this to
 * change a password is safe.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin
                            {--email= : The address to sign in with}
                            {--password= : Their password; prompted for if omitted}
                            {--name= : Their name}';

    protected $description = 'Create or update the platform administrator';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password');
        $name = $this->option('name') ?: 'Platform Admin';

        $validator = Validator::make(
            compact('email', 'password', 'name'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $user->exists ? $user->name : $name,
            'password' => Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_super_admin' => true,
        ])->save();

        $this->newLine();
        $this->components->info($user->wasRecentlyCreated
            ? 'Platform administrator created.'
            : 'Existing account promoted, password updated.');
        $this->components->twoColumnDetail('Email', $user->email);
        $this->components->twoColumnDetail('Sign in at', rtrim(config('app.url'), '/').'/majedul');
        $this->newLine();

        return self::SUCCESS;
    }
}
