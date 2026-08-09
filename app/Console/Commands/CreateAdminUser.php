<?php

namespace App\Console\Commands;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Creates the first user on a fresh installation.
 *
 * Registration is disabled — people arrive through team invitations — so
 * without this a freshly migrated production database has no way in at all.
 * `db:seed` cannot fill the gap: the seeder builds users through factories,
 * and factories need fakerphp/faker, which is a dev dependency and absent from
 * the production image by design.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin
                            {--name= : The person\'s name}
                            {--email= : Their email address, used to sign in}
                            {--password= : Their password; prompted for if omitted}
                            {--company= : Company name (defaults to "<name>\'s Company")}';

    protected $description = 'Create the first admin user and their company';

    public function handle(CreateTeam $createTeam): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password');
        $company = $this->option('company') ?: "{$name}'s Company";

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use ($createTeam, $name, $email, $password, $company): User {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                ]);

                // forceFill, because `email_verified_at` is deliberately not
                // mass-assignable. Verified on creation: there is nobody to
                // send a verification link to yet, and the dashboard sits
                // behind the `verified` middleware.
                $user->forceFill(['email_verified_at' => now()])->save();

                $createTeam->handle($user, $company, isPersonal: true);

                return $user;
            });
        } catch (ValidationException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $team = $user->fresh()->currentTeam;

        $this->newLine();
        $this->components->info('Admin user created.');
        $this->components->twoColumnDetail('Email', $user->email);
        $this->components->twoColumnDetail('Company', $team->name ?? '—');
        $this->components->twoColumnDetail('Sign in at', rtrim(config('app.url'), '/').'/');
        $this->newLine();

        return self::SUCCESS;
    }
}
