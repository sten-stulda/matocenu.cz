<?php

namespace App\Console\Commands;

use App\Domain\Identity\Actions\RecordConsents;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('matocenu:create-admin {email} {name} {--role=superadmin : superadmin|admin|moderator|editor|analyst|ai_manager|license_manager|product_manager}')]
#[Description('Založí účet správce (nebo přidělí roli existujícímu účtu) a vypíše jednorázové heslo.')]
class CreateAdmin extends Command
{
    public function handle(RecordConsents $consents): int
    {
        $role = Role::tryFrom((string) $this->option('role'));
        if (! $role || $role === Role::User) {
            $this->error('Neznámá role.');

            return self::FAILURE;
        }
        $email = Str::lower((string) $this->argument('email'));
        $password = null;

        DB::transaction(function () use ($email, $role, &$password) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $password = Str::password(16, symbols: false);
                $user = new User(['name' => (string) $this->argument('name'), 'email' => $email, 'password' => $password]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->settings()->create([]);
            }
            $user->assignRole(Role::User->value, $role->value);
        });

        $this->info("Role {$role->label()} přidělena účtu {$email}.");
        if ($password) {
            $this->warn("Jednorázové heslo (změň ho po přihlášení): {$password}");
            $this->line('Při prvním přihlášení bude potřeba odsouhlasit podmínky.');
        }

        return self::SUCCESS;
    }
}
