<?php

namespace App\Support;

use App\Enums\Permission as P;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AccessControl
{
    public const ADMIN_ROLE = 'Administrateur';

    /** Rôles créés à l'installation, avec leurs permissions initiales (modifiables ensuite dans l'application). */
    public const DEFAULT_ROLES = [
        self::ADMIN_ROLE => [P::ManageUsers, P::ManageRoles],
        'Direction' => [P::ViewAppointments, P::DecideAppointments, P::CancelAppointments],
        'Sécurité' => [P::ViewVisits, P::RecordArrivals],
    ];

    /** Permissions que le rôle Administrateur conserve toujours (évite de perdre l'accès à l'administration). */
    public const PROTECTED_ADMIN_PERMISSIONS = [P::ManageUsers, P::ManageRoles];

    /**
     * Crée les permissions manquantes et les rôles par défaut absents.
     * Les rôles déjà existants ne sont pas modifiés, pour respecter les réglages faits dans l'application.
     */
    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (P::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (self::DEFAULT_ROLES as $name => $permissions) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if (! $role) {
                Role::create(['name' => $name, 'guard_name' => 'web'])
                    ->syncPermissions(array_map(fn (P $p) => $p->value, $permissions));
            }
        }

        $admin = Role::findByName(self::ADMIN_ROLE, 'web');
        $admin->givePermissionTo(array_map(fn (P $p) => $p->value, self::PROTECTED_ADMIN_PERMISSIONS));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Nombre de comptes actifs qui peuvent encore gérer les utilisateurs. */
    public static function activeUserManagers(): int
    {
        return User::where('is_active', true)->permission(P::ManageUsers->value)->count();
    }

    /**
     * Espace d'accueil d'un utilisateur selon ses permissions.
     *
     * @return array<string, array{route: string, label: string}>
     */
    public static function spacesFor(User $user): array
    {
        $spaces = [
            'direction' => [P::ViewAppointments, 'Rendez-vous'],
            'security' => [P::ViewVisits, 'Contrôle d\'accès'],
            'admin.users' => [P::ManageUsers, 'Utilisateurs'],
            'admin.roles' => [P::ManageRoles, 'Rôles et permissions'],
        ];

        return collect($spaces)
            ->filter(fn ($space) => $user->can($space[0]->value))
            ->map(fn ($space, $route) => ['route' => $route, 'label' => $space[1]])
            ->all();
    }

    public static function homeRouteFor(User $user): ?string
    {
        return array_key_first(self::spacesFor($user));
    }
}
