<?php

namespace App\Enums;

/**
 * Permissions de l'application (gérées avec spatie/laravel-permission).
 * Ajouter un cas ici puis lancer « php artisan app:permissions » pour le créer en base.
 */
enum Permission: string
{
    case ViewAppointments = 'appointments.view';
    case DecideAppointments = 'appointments.decide';
    case CancelAppointments = 'appointments.cancel';
    case ViewVisits = 'visits.view';
    case RecordArrivals = 'visits.check-in';
    case ManageUsers = 'users.manage';
    case ManageRoles = 'roles.manage';

    public function label(): string
    {
        return match ($this) {
            self::ViewAppointments => 'Consulter toutes les demandes de rendez-vous',
            self::DecideAppointments => 'Valider ou refuser les demandes',
            self::CancelAppointments => 'Annuler un rendez-vous validé',
            self::ViewVisits => 'Consulter les rendez-vous du jour (contrôle d\'accès)',
            self::RecordArrivals => 'Pointer l\'arrivée des visiteurs',
            self::ManageUsers => 'Gérer les comptes utilisateurs',
            self::ManageRoles => 'Gérer les rôles et leurs permissions',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::ViewAppointments, self::DecideAppointments, self::CancelAppointments => 'Agenda du PDG',
            self::ViewVisits, self::RecordArrivals => 'Sécurité',
            self::ManageUsers, self::ManageRoles => 'Administration',
        };
    }

    /** @return array<string, list<self>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }

    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
    }
}
