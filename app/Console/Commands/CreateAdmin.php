<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ReadsSecretFromStdin;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * The first admin of a fresh install: a user holding super-admin globally.
 *
 * A new install otherwise has no way in. Run by the deploy control plane on
 * the first deploy, with the password on stdin. Idempotent: an existing user
 * keeps their password and only gains super-admin if they lack it, so running
 * it again can never lock anyone out or reset a changed password.
 */
class CreateAdmin extends Command
{
    use ReadsSecretFromStdin;

    protected $signature = 'app:create-admin
                            {email : The admin\'s email}
                            {name : The admin\'s name}
                            {--password-stdin : Read the password from stdin}';

    protected $description = 'Create the first super-admin, with the password read from stdin.';

    public function handle(): int
    {
        if (! $this->option('password-stdin')) {
            $this->error('Pass the password on stdin with --password-stdin.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not an email address.');

            return self::FAILURE;
        }

        $role = Role::where('name', 'super-admin')->first();

        if ($role === null) {
            $this->error('The super-admin role does not exist. Run RolesAndPermissionsSeeder first.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
                $this->info("{$email} already exists; granted super-admin.");
            } else {
                $this->info("{$email} already exists; nothing changed.");
            }

            return self::SUCCESS;
        }

        $password = $this->secretFromStdin();

        if (strlen($password) < 12) {
            $this->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role);

        $this->info("Created {$email} as super-admin.");

        return self::SUCCESS;
    }
}
