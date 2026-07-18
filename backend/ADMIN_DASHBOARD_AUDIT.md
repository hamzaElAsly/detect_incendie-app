# Audit du Dashboard Admin

Date de l'audit : 17 juillet 2026

## Synthese executive

Le projet est une API Laravel 12.64.0 sous PHP 8.2.12, connectee a MySQL (`laravel_fire`). Le domaine incendie est modelise, mais l'affirmation selon laquelle le backend serait entierement termine n'est pas confirmee par le depot : le RBAC est un systeme maison et non Spatie, une seule Policy existe, plusieurs services/actions demandes n'existent pas, et le fichier de routes metier `routes/platform.php` n'est pas enregistre dans le bootstrap. Le multi-tenant est correctement controle sur l'unique route API tenant active, mais les modeles ne possedent pas de global scope : toute nouvelle requete admin doit donc etre explicitement scopee.

Filament n'etait pas installe. La version compatible verifiee est Filament 5 (PHP 8.2+, Laravel 11.28+). La tentative d'installation `filament/filament:~5.0` a echoue car l'environnement interdit l'acces a Packagist ; Composer a restaure `composer.json` et `composer.lock`.

## Architecture actuelle

- Framework : Laravel 12.64.0 ; PHP 8.2.12.
- Authentification API : tokens proprietaires SHA-256 via `AuthenticateApiToken`, sans Sanctum ni Passport.
- Authentification web : guard `web` Laravel et sessions en base disponibles, mais aucun ecran de connexion admin.
- Tenancy : relation many-to-many `factory_user`, en-tete `X-Factory-ID`, middleware `ResolveFactoryContext`, service singleton `FactoryContext`.
- Identifiants : ULID pour les entites metier ; identifiants numeriques pour les utilisateurs, roles et permissions.
- RBAC : tables locales `roles`, `permissions`, `role_user`, `permission_role` et roles seeds. Ce n'est pas Spatie Permission.
- Donnees : Factory, User, Zone, Camera, CameraCredential, CameraHealthLog, FireEvent, FireEventDetection, Alert, AlertRecipient, Notification, NotificationLog, FactorySetting, Plan, Subscription, ApiClient, ApiToken et AuditLog.
- Fichiers : disque Laravel `local`; medias ranges sous `incidents/{factory_id}/{event_id}` avec URL temporaire si le driver le permet.
- Queue : driver `database`.
- Notifications : modeles et enums seulement ; aucun Job ni service d'envoi/retry trouve.
- Admin panel : absent.

## Composants reutilisables

- Enums : `FireEventType`, `FireEventStatus`, `AlertStatus`, `NotificationStatus`, `NotificationChannel`, `RecordStatus`, `Severity`, `RiskLevel`.
- `DashboardService` : statistiques usine, graphique temporel, statuts camera, evenements recents et metriques par zone/severite.
- `FactorySettingsService` : lecture et mise a jour transactionnelle des parametres usine.
- `IncidentMediaService` : stockage tenant-aware des images/videos et generation d'URL.
- `AuditService` : journalisation avec masquage des secrets.
- Chiffrement des `CameraCredential` et du `Camera::stream_url`; attributs sensibles caches a la serialisation.
- Scopes locaux `forFactory()` sur les modeles utilisant `BelongsToFactory`.
- Roles existants : `super_admin`, `platform_admin`, `factory_owner`, `factory_manager`, `safety_officer`, `security_agent`, `maintenance_agent`, `viewer`.
- Permissions existantes : factories, users, zones, cameras, events, alerts, notifications, settings, reports et audit logs.

## Ecarts fonctionnels

- Filament et Livewire absents.
- Aucune interface web admin, aucun profil admin et aucun changement de mot de passe depuis un panel.
- `User` n'expose aucune relation roles/permissions ni methode centralisee `can()`/`hasPermission()`.
- Une seule `FactoryPolicy`; aucune Policy pour User, Zone, Camera, FireEvent, Alert, Notification, Plan, Subscription, Settings ou AuditLog.
- `FactoryPolicy` utilise encore le role pivot `factory_admin`, absent de la liste officielle.
- `FireEventWorkflowService`, pourtant obligatoire pour les transitions, absent.
- Aucun service de test de connexion camera, de heartbeat ou de retry de notification.
- Aucun Job de notification trouve.
- Relations `Factory` tres incompletes pour les vues detaillees demandees.
- Champs enrichis de Factory presents en base mais absents de `$fillable`.
- Invitations, reset administratif, protection du dernier owner et archivage usine non implementes.
- Aucun dashboard plateforme.
- Aucun rapport telechargeable.
- `routes/platform.php` n'est pas charge par `bootstrap/app.php`; ses routes dashboard/settings/audit/media sont inaccessibles.
- Les permissions plans, abonnements, destinataires et API clients n'existent pas encore dans le seeder.
- Les tests ne couvrent ni Policies/RBAC, ni transitions d'evenement, ni isolation tenant des futures Resources.

## Risques de securite

1. **Critique — autorisations insuffisantes.** Les FormRequest settings/media retournent `authorize(): true` et les routes metier n'appliquent aucune permission fine.
2. **Critique — fuite cross-tenant potentielle dans l'admin.** `BelongsToFactory` fournit seulement un scope manuel, sans global scope. Une Resource oubliee peut lire toutes les usines.
3. **Eleve — RBAC non operationnel.** Les tables sont presentes, mais `User` ne les relie pas et les permissions ne sont pas verifiees.
4. **Eleve — incoherence super-admin.** Le booleen `is_super_admin` et le role `super_admin` peuvent diverger.
5. **Eleve — comptes plateforme.** Le seeder donne actuellement toutes les permissions au `platform_admin`, y compris suppression et credentials, contrairement aux restrictions demandees.
6. **Eleve — routes dormantes.** Les controles supposes dans `routes/platform.php` ne protegent rien tant que ce fichier n'est pas enregistre.
7. **Moyen — media local.** Une URL publique de fallback peut exposer un incident si le disque est public; le telechargement doit passer par une action autorisee.
8. **Moyen — secrets camera.** Le chiffrement est bon, mais toute action admin doit interdire affichage, recherche, export et audit en clair.
9. **Moyen — absence d'audit automatique.** Le service existe mais les mutations ne l'appellent pas systematiquement.

## Plan d'implementation recommande

1. Installer Filament 5 des que Packagist est accessible, puis `php artisan filament:install --panels` sans scaffold applicatif.
2. Centraliser le RBAC dans `User` et un catalogue unique de permissions; aligner le booleen super-admin avec le role sans dupliquer les decisions.
3. Ajouter les Policies de chaque entite et des tests de matrice role/action, notamment les interdictions super-admin, dernier owner et lecture seule Viewer.
4. Creer un resolver de tenant admin : choix d'usine pour les roles plateforme, usines membres pour les autres, et scope obligatoire de toutes les Resources/relations/options.
5. Implementer les services manquants avant les boutons : workflow FireEvent, acknowledgement Alert, test Camera et retry Notification. Toutes les mutations doivent etre transactionnelles et auditees.
6. Configurer `/admin`, le login session, `canAccessPanel`, profil, mot de passe, theme et navigation conditionnee par permission.
7. Implementer les dashboards par role en reutilisant `DashboardService`, puis ajouter un service de statistiques plateforme.
8. Ajouter les Resources dans cet ordre : Factory, User, Zone, Camera/Health, FireEvent, Alert, Notification, Recipient, Plan, Subscription, ApiClient, AuditLog, Settings.
9. Enregistrer explicitement `routes/platform.php` apres ajout des Policies/middleware adequats, sans modifier les URI API existantes.
10. Ajouter des tests Filament/Livewire, tests cross-tenant pour chaque Resource, tests des actions sensibles, puis executer migrations MySQL, tests et build frontend.

## Validation realisee

- `php artisan --version` : Laravel 12.64.0.
- `php artisan route:list --except-vendor` : 9 routes actives ; aucune route admin.
- `php artisan test` : 9 tests passes, 30 assertions.
- Avertissement non bloquant : `.phpunit.result.cache` n'est pas inscriptible dans le sandbox.
- Migration `2026_07_17_000002_create_fire_detection_domain` detectee en attente. Son execution reelle avec `php artisan migrate --force` echoue sur MySQL avec `SQLSTATE[42S21] Duplicate column name 'phone'` : la base contient donc au moins une partie du schema sans entree correspondante dans la table `migrations`. La migration reste `Pending` et doit etre reconciliee avec un inventaire complet du schema avant toute nouvelle migration.

## Blocage externe

L'implementation du panel ne peut pas continuer de facon executable dans cet environnement tant que `https://repo.packagist.org` est inaccessible. Il ne faut pas creer de classes Filament sans la dependance, car cela casserait l'autoload et les tests. Une fois le reseau autorise (ou les paquets fournis dans un cache Composer/vendor), reprendre avec Filament 5 et le plan ci-dessus.
