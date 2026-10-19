<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('artsci:admin:create {name} {email}')]
#[Description('Create the first platform administrator through a protected console prompt')]
class CreateInitialAdministrator extends Command
{
    public function handle(RolePermissionSeeder $rolePermissionSeeder): int
    {
        if (User::query()->whereHas('platformRoles', fn ($query) => $query->where('slug', 'super_admin'))->exists()) {
            $this->error('A platform administrator already exists.');

            return self::FAILURE;
        }

        $name = trim((string) $this->argument('name'));
        $email = Str::lower(trim((string) $this->argument('email')));
        $validator = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = $this->secret('Choose a password (at least 14 characters)');
        $confirmation = $this->secret('Confirm password');

        if (! is_string($password) || mb_strlen($password) < 14 || $password !== $confirmation) {
            $this->error('The password must be at least 14 characters and the confirmation must match.');

            return self::FAILURE;
        }

        $rolePermissionSeeder->run();

        $created = DB::transaction(function () use ($name, $email, $password): bool {
            $role = Role::query()->where('slug', 'super_admin')->where('scope', 'platform')->lockForUpdate()->firstOrFail();

            if (User::query()->whereHas('platformRoles', fn ($query) => $query->where('slug', 'super_admin'))->exists()) {
                return false;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->platformRoles()->attach($role->getKey());

            return true;
        });

        if (! $created) {
            $this->error('A platform administrator was created while this command was running.');

            return self::FAILURE;
        }

        $this->info('Platform administrator created. Sign in through the ARTSCI frontend.');

        return self::SUCCESS;
    }
}
