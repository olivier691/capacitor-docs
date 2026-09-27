# Prise de rendez-vous avec le PDG

Application web responsive (smartphone et ordinateur) qui gère les rendez-vous du PDG :

1. **Formulaire public** (`/`) : le demandeur saisit son identité, son organisation, ses coordonnées,
   l'objet, la date, l'heure, la durée et ses accompagnants.
2. **Notification** : la demande est envoyée par e-mail au PDG et à son entourage habilité
   (`NOTIFY_EMAILS`). Le demandeur reçoit un accusé de réception avec sa référence.
3. **Espace direction** (`/direction`) : le PDG et son entourage valident (en ajustant le créneau si besoin),
   refusent ou annulent. Les chevauchements avec un rendez-vous déjà validé sont signalés.
4. **Agenda Outlook** : à la validation, l'événement est créé dans le calendrier Outlook du PDG via Microsoft Graph.
   Une annulation le retire. Le demandeur est informé par e-mail.
5. **Espace sécurité** (`/securite`) : **lecture seule** des rendez-vous validés du jour (ou d'une autre date).
   L'agent coche « Venu » quand le visiteur se présente : l'heure d'arrivée et l'agent sont enregistrés
   et visibles par la direction. La sécurité ne voit ni l'objet ni les coordonnées du visiteur.

## Démarrage

Prérequis : **Node.js 22.5 ou plus récent** (la base SQLite intégrée à Node est utilisée).

```bash
cd appointment-app
npm install
cp .env.example .env        # puis compléter les valeurs
npm run add-user -- assistante direction "Assistante du PDG"
npm run add-user -- pdg direction "Président Directeur Général"
npm run add-user -- securite securite "Poste de garde"
npm start                   # http://localhost:3000
```

Sans configuration Microsoft Graph, l'application fonctionne en **mode simulation** : les e-mails et
événements d'agenda sont seulement affichés dans la console. C'est pratique pour tester.

Tests : `npm test`.

## Rôles

| Rôle | Accès |
|------|-------|
| `direction` | PDG et entourage habilité : voit toutes les demandes, valide, refuse, annule. |
| `securite` | Consultation des rendez-vous validés, pointage des arrivées uniquement. |

Les comptes sont stockés dans `config/users.json` (mots de passe hachés avec scrypt, 12 caractères minimum).
Relancez `npm run add-user` avec le même identifiant pour changer un mot de passe. Redémarrez l'application
après toute modification des comptes.

## Configuration de Microsoft 365 (Outlook)

1. Dans le [portail Microsoft Entra](https://entra.microsoft.com) : **Applications > Inscriptions
   d'applications > Nouvelle inscription** (ex. « Prise de rendez-vous »).
2. Notez l'**ID d'annuaire (tenant)** et l'**ID d'application (client)** → `GRAPH_TENANT_ID`, `GRAPH_CLIENT_ID`.
3. **Certificats et secrets > Nouveau secret client** → `GRAPH_CLIENT_SECRET`.
4. **Autorisations de l'API > Microsoft Graph > Autorisations d'application** : ajoutez
   `Calendars.ReadWrite` et `Mail.Send`, puis **Accorder le consentement administrateur**.
5. **Recommandé** : limitez l'application aux seules boîtes du PDG et de l'expéditeur avec une stratégie
   d'accès Exchange Online. Sans cela, elle pourrait accéder à toutes les boîtes de l'organisation :

   ```powershell
   New-DistributionGroup -Name "App-RendezVous" -Type Security -Members pdg@mon-entreprise.com,rendez-vous@mon-entreprise.com
   New-ApplicationAccessPolicy -AppId <GRAPH_CLIENT_ID> -PolicyScopeGroupId App-RendezVous@mon-entreprise.com -AccessRight RestrictAccess -Description "Rendez-vous PDG"
   ```

Les rendez-vous validés apparaissent dans l'agenda Outlook du PDG avec la catégorie « Rendez-vous »
et un rappel 15 minutes avant. L'entourage qui a déjà une délégation sur cet agenda les voit aussi.

## Déploiement

- Placez l'application derrière un proxy HTTPS (IIS, Nginx, Azure App Service…) avec `NODE_ENV=production`.
  Les cookies de session sont alors marqués `Secure`.
- Sauvegardez régulièrement le dossier `data/` (base SQLite).
- `SESSION_SECRET` est obligatoire en production.

## Sécurité intégrée

- Sessions signées (HMAC), cookies `HttpOnly` et `SameSite=Strict`, expiration après 10 h.
- Contrôle d'origine et JSON obligatoire sur les requêtes modifiantes (protection CSRF).
- En-têtes CSP stricts, aucun script en ligne.
- Limite de 5 demandes par 15 minutes et par adresse IP sur le formulaire, et de 10 tentatives de connexion.
- Les échecs de création dans Outlook bloquent la validation, pour que l'application et l'agenda restent cohérents.
