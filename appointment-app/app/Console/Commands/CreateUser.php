<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'app:user
        {email? : Adresse e-mail (identifiant de connexion)}
        {--name= : Nom affiché}
        {--role=* : Rôle(s) à attribuer (ex. --role=Administrateur --role=Direction)}';

    protected $description = 'Crée un compte ou met à jour le mot de passe et les rôles d\'un compte existant';

    public function handle(): int
    {
        $email = Str::lower($this->argument('email') ?? text('Adresse e-mail', required: true));
        $existing = User::firstWhere('email', $email);
        $available = Role::orderBy('name')->pluck('name')->all();

        $name = $this->option('name') ?? $existing?->name ?? text('Nom affiché', required: true);
        $roles = $this->option('role') ?: multiselect('Rôles', $available, default: $existing?->getRoleNames()->all() ?? []);
        $password = password('Mot de passe (12 caractères minimum)', required: true);

        $validator = Validator::make(compact('email', 'name', 'roles', 'password'), [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:120'],
            'roles' => ['array'],
            'roles.*' => ['in:'.implode(',', $available)],
            'password' => ['required', 'string', 'min:12'],
        ], ['roles.*.in' => 'Rôle inconnu : :input. Rôles disponibles : '.implode(', ', $available).'.']);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'is_active' => true]);
        $user->syncRoles($roles);

        $this->info("Compte « {$user->email} » ".($existing ? 'mis à jour' : 'créé')
            .' — rôles : '.($user->getRoleNames()->implode(', ') ?: 'aucun').'.');

        return self::SUCCESS;
    }
}
