<?php

namespace App\Console\Commands;

use App\Auth\PasswordRule;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'asl:create-admin';

    protected $description = 'Create the first super administrator. The password is prompted or read from the environment.';

    public function handle(AuditLogger $audit): int
    {
        $this->call('db:seed', [
            '--class' => RolePermissionSeeder::class,
            '--force' => true,
        ]);

        [$name, $email, $password] = $this->credentials();

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', PasswordRule::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->where('slug', 'super-administrator')->firstOrFail();
        $user->roles()->attach($role);

        $audit->record('user_created', $user, newValues: [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['super-administrator'],
            'source' => 'asl:create-admin',
        ], actor: $user);

        $this->info('Super administrator created for '.$user->email.'.');
        $this->comment('If ADMIN_PASSWORD was exported for this command, unset it now.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function credentials(): array
    {
        if (! $this->input->isInteractive()) {
            return [
                trim((string) getenv('ADMIN_NAME')),
                strtolower(trim((string) getenv('ADMIN_EMAIL'))),
                (string) getenv('ADMIN_PASSWORD'),
            ];
        }

        $name = trim((string) $this->ask('Name'));
        $email = strtolower(trim((string) $this->ask('Email')));
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        if ($password !== $confirmation) {
            $this->error('The password confirmation does not match.');

            return ['', '', ''];
        }

        return [$name, $email, $password];
    }
}
