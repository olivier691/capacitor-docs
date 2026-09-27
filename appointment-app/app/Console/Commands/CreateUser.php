<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'app:user
        {email? : Adresse e-mail (identifiant de connexion)}
        {--name= : Nom affiché}
        {--role= : direction ou security}';

    protected $description = 'Crée un compte (ou change le mot de passe / rôle d\'un compte existant)';

    public function handle(): int
    {
        $email = Str::lower($this->argument('email') ?? text('Adresse e-mail', required: true));
        $existing = User::firstWhere('email', $email);

        $name = $this->option('name') ?? $existing?->name ?? text('Nom affiché', required: true);
        $role = $this->option('role') ?? select('Rôle', collect(Role::cases())->mapWithKeys(
            fn (Role $r) => [$r->value => $r->label()]
        )->all(), default: $existing?->role->value);
        $password = password('Mot de passe (12 caractères minimum)', required: true);

        $validator = Validator::make(compact('email', 'name', 'role', 'password'), [
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:'.implode(',', array_column(Role::cases(), 'value'))],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], compact('name', 'role', 'password'));
        $this->info("Compte « {$user->email} » ({$user->role->label()}) ".($existing ? 'mis à jour.' : 'créé.'));

        return self::SUCCESS;
    }
}
