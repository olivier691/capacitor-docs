# Prise de rendez-vous avec le PDG

Application **Laravel 13 + Livewire 4** (Tailwind CSS 4), responsive smartphone et ordinateur.

| Espace | URL | Qui | Ce qu'on y fait |
|--------|-----|-----|-----------------|
| Formulaire public | `/` | Tout visiteur | Demande de rendez-vous : identité, organisation, coordonnées, objet, date, heure, durée, accompagnants, consentement. |
| Direction | `/direction` | PDG et entourage habilité (rôle `Direction`) | Voir les demandes, **valider** (en ajustant le créneau), **refuser**, **annuler**. Les chevauchements sont signalés. |
| Sécurité | `/securite` | Service de sécurité (rôle `Sécurité`) | **Lecture seule** des rendez-vous validés par date, et case **« Venu »** à cocher le jour même. |
| Administration | `/admin/utilisateurs`, `/admin/roles` | Rôle `Administrateur` | Créer, modifier et désactiver les comptes ; créer des rôles et choisir leurs permissions. |

## Déroulement

1. Le visiteur envoie le formulaire. Le PDG et son entourage (`APPOINTMENTS_NOTIFY_EMAILS`) reçoivent un e-mail.
   Le visiteur reçoit un accusé de réception avec sa référence (`RDV-20261005-XXXXXX`).
2. Un membre de la direction valide la demande. L'événement est **créé dans l'agenda Outlook du PDG**
   (Microsoft Graph), avec un rappel 15 min avant, et le visiteur reçoit une confirmation.
   Si Outlook est injoignable, rien n'est modifié et l'application invite à réessayer.
3. Une annulation retire l'événement de l'agenda Outlook.
4. Le jour J, l'agent de sécurité coche « Venu ». L'heure et le nom de l'agent sont enregistrés et visibles par la direction.
   La sécurité ne voit ni l'objet du rendez-vous, ni l'e-mail, ni le téléphone du visiteur.

## Installation

Prérequis : PHP 8.3+, Composer, Node.js 20+. La base est SQLite par défaut ; MySQL, PostgreSQL et SQL Server
fonctionnent aussi via `DB_CONNECTION`.

```bash
cd appointment-app
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate

# Premier compte administrateur (le mot de passe est demandé, 12 caractères minimum).
# Les autres comptes se créent ensuite dans l'application, menu « Utilisateurs ».
php artisan app:user admin@entreprise.com --name="Service informatique" --role=Administrateur

php artisan serve
```

Relancer `php artisan app:user` avec la même adresse change le mot de passe et les rôles. C'est aussi le moyen de
récupérer l'accès si plus personne ne peut se connecter en administrateur.

Pour tester avec des données fictives : `php artisan migrate:fresh --seed`. Les comptes de démonstration sont
`admin@example.com` / `admin-demo`, `direction@example.com` / `direction-demo` et `securite@example.com` /
`securite-demo`. Ne pas utiliser en production.

## Rôles et permissions (spatie/laravel-permission)

Les droits reposent sur des **permissions**, regroupées en **rôles**. Un compte peut avoir plusieurs rôles :
par exemple, l'assistante du PDG peut être à la fois `Direction` et `Administrateur`.

| Permission | Autorise | Rôle par défaut |
|------------|----------|-----------------|
| `appointments.view` | Voir toutes les demandes (espace direction) | Direction |
| `appointments.decide` | Valider ou refuser une demande | Direction |
| `appointments.cancel` | Annuler un rendez-vous validé | Direction |
| `visits.view` | Voir les rendez-vous du jour (espace sécurité) | Sécurité |
| `visits.check-in` | Cocher « Venu » | Sécurité |
| `users.manage` | Gérer les comptes | Administrateur |
| `roles.manage` | Gérer les rôles et leurs permissions | Administrateur |

- Les permissions et les trois rôles par défaut sont créés par `php artisan migrate`. Ensuite, les rôles se modifient
  dans l'application, menu « Rôles et permissions ». On peut par exemple créer un rôle « Accueil » qui consulte les
  visites sans pouvoir les pointer.
- Chaque page et chaque action est contrôlée côté serveur, pas seulement l'affichage des boutons.
- Garde-fous : le rôle `Administrateur` ne peut être ni supprimé, ni renommé, ni privé de la gestion des comptes et
  des rôles. Il reste toujours au moins un compte actif capable de gérer les utilisateurs, et on ne peut pas
  désactiver son propre compte.
- Un compte **désactivé** ne peut plus se connecter et il est déconnecté immédiatement ; son historique est conservé.
  Un compte sans aucun rôle n'a accès à rien.
- Pour ajouter une permission : créer un nouveau cas dans `app/Enums/Permission.php`, puis lancer
  `php artisan app:permissions`.

Tests : `php artisan test`.

### Mode simulation

Sans identifiants Microsoft Graph et avec `MAIL_MAILER=log`, les e-mails et les événements d'agenda sont
seulement écrits dans `storage/logs/laravel.log`. C'est pratique pour tester sans Microsoft 365.

## Configuration de Microsoft 365 (Outlook)

1. [Portail Microsoft Entra](https://entra.microsoft.com) → **Applications → Inscriptions d'applications →
   Nouvelle inscription** (ex. « Rendez-vous PDG »).
2. Reporter l'**ID d'annuaire (tenant)** et l'**ID d'application (client)** dans `MS_GRAPH_TENANT_ID` et `MS_GRAPH_CLIENT_ID`.
3. **Certificats et secrets → Nouveau secret client** → `MS_GRAPH_CLIENT_SECRET`.
4. **Autorisations de l'API → Microsoft Graph → Autorisations d'application** : `Calendars.ReadWrite` et
   `Mail.Send`, puis **Accorder le consentement administrateur**.
5. Dans `.env` :
   - `MS_GRAPH_CALENDAR_USER` = boîte du PDG (l'agenda qui reçoit les rendez-vous) ;
   - `MAIL_MAILER=microsoft-graph` et `MAIL_FROM_ADDRESS` = boîte d'envoi (ex. `rendez-vous@entreprise.com`).
6. **Fortement recommandé** : restreindre l'application à ces deux boîtes. Sans cela, elle a accès à toutes les
   boîtes de l'organisation (Exchange Online PowerShell) :

   ```powershell
   New-DistributionGroup -Name "App-RendezVous" -Type Security -Members pdg@entreprise.com,rendez-vous@entreprise.com
   New-ApplicationAccessPolicy -AppId <MS_GRAPH_CLIENT_ID> -PolicyScopeGroupId App-RendezVous@entreprise.com `
       -AccessRight RestrictAccess -Description "Rendez-vous PDG"
   ```

Le fuseau horaire des rendez-vous et de l'agenda est `APP_TIMEZONE` (ex. `Africa/Abidjan`, `Europe/Paris`).

## Structure du code

| Fichier | Rôle |
|---------|------|
| `app/Livewire/BookingForm.php` | Formulaire public (validation, anti-abus 5 demandes / 15 min par IP) |
| `app/Livewire/Auth/Login.php` | Connexion (limitée à 5 essais) |
| `app/Livewire/Direction/Appointments.php` | Espace direction |
| `app/Livewire/Security/Visits.php` | Espace sécurité |
| `app/Services/AppointmentService.php` | Règles métier : soumission, validation, refus, annulation, pointage |
| `app/Services/OutlookCalendar.php` | Création et suppression des événements Outlook |
| `app/Services/MicrosoftGraph/` | Client Graph (jeton OAuth mis en cache) et transport mail `microsoft-graph` |
| `app/Mail/`, `resources/views/mail/` | E-mails envoyés |
| `app/Livewire/Admin/Users.php`, `Roles.php` | Administration des comptes et des rôles |
| `app/Enums/Permission.php`, `app/Support/AccessControl.php` | Liste des permissions, rôles par défaut, garde-fous |

## Mise en production

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS obligatoire (IIS, Nginx, Azure App Service…).
- `php artisan config:cache route:cache view:cache` après chaque déploiement.
- Sauvegarder régulièrement la base de données.
