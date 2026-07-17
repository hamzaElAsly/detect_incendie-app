# Audit backend — 17 juillet 2026

## État initial

- Laravel 12.64.0, PHP 8.2.12, Composer 2.8.4.
- Squelette Laravel neuf, sans routes API, contrôleurs métier ni authentification par jeton.
- Modèle `User` et migrations standard seulement (`users`, sessions, cache et jobs).
- PHPUnit 11 configuré avec SQLite en mémoire pour les tests.
- `.env` local configuré pour MySQL ; `pdo_mysql` est chargé. `.env.example` utilisait encore SQLite.
- Queue et cache `database`. Extension Redis et POSIX absentes sous Windows : Horizon n’est donc pas activé.
- Aucun middleware applicatif, Policy, Form Request, API Resource ni test métier.
- Route web Blade de bienvenue conservée afin de ne supprimer aucune fonctionnalité existante ; toutes les nouvelles fonctions sont API-only.

## Fichiers existants audités

`composer.json`, `composer.lock`, `bootstrap/app.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, toutes les configurations, migrations, routes et tests présents sous `backend/`. Le dossier Python est hors du périmètre de modification.

## Problèmes et risques trouvés

- Isolation tenant inexistante, identifiants utilisateurs séquentiels exposés et aucune autorisation métier.
- Pas de statut utilisateur, dernière connexion, usine, rôle ou token API.
- Pas de contrat OpenAPI ni de documentation des routes.
- Sanctum, Spatie Permission et un générateur OpenAPI ne sont ni verrouillés ni installés.
- Packagist est inaccessible depuis l’environnement d’exécution. La commande tentée (`composer require laravel/sanctum spatie/laravel-permission dedoc/scramble`) échoue sur la connexion réseau ; aucun faux package n’a été ajouté au lockfile.
- Redis/posix indisponibles : Horizon serait inadapté à ce poste Windows. Telescope n’est pas nécessaire au socle et devra rester local uniquement.

## Architecture proposée

- API versionnable : Controllers minces, Form Requests, API Resources, Actions transactionnelles et Services.
- Base MySQL partagée, tables métier portant `factory_id`, ULID pour les ressources exposées.
- `X-Factory-ID` comme stratégie tenant unique. `FactoryContext` est request-scoped via un singleton vidé après chaque requête ; le middleware refuse usine absente, inactive ou étrangère.
- Policies pour l’autorisation sur les ressources, rôles tenant-scopés. Le rôle ne doit jamais être global à l’utilisateur.
- Credentials caméra chiffrés via casts Laravel `encrypted` et jamais renvoyés par une Resource.
- Réception agent future via clé par agent hachée, idempotency key et signature/replay protection ; traitement lourd en queue.
- Queue `database` en développement, Redis/Horizon en production Linux lorsqu’ils seront disponibles.

## Implémenté dans cette étape

- Auth complète demandée, jetons Bearer aléatoires stockés uniquement sous SHA-256, expiration et rotation.
- Inscription transactionnelle créant utilisateur, usine ULID et adhésion `factory_owner`.
- Statuts actif/inactif, dernière connexion, reset password avec révocation des jetons, rate limits.
- Contexte tenant et Policy d’usine, documentation de toutes les routes actuelles.
- Tests d’authentification et d’isolation tenant.

## Plan d’implémentation

1. Dès accès Packagist : installer Sanctum, Spatie Permission et Scramble, publier leurs migrations/configurations, migrer les jetons/rôles locaux sans casser le contrat API.
2. Ajouter membres/invitations, zones et caméras (credentials chiffrés), avec Policies et index composites tenant.
3. Ajouter endpoint agent authentifié, événements idempotents, médias, alertes et jobs de notification.
4. Ajouter heartbeat/état caméra, dashboard agrégé, paramètres, audit immuable.
5. Préparer plans, abonnements, quotas et webhooks de facturation sans coupler le domaine à un fournisseur.
6. Générer et publier OpenAPI, compléter tests MySQL d’intégration, CI, analyse statique et contrôles de sécurité.
