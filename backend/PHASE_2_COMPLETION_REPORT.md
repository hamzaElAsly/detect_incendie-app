# Rapport de finalisation — Phase 2

Date : 29 juillet 2026

## 1. Analyse initiale

L'analyse a confirmé six modules déjà terminés et quatre modules incomplets : Users, API Clients, Camera Health et Profile. Le projet utilise deux mécanismes distincts mais cohérents selon l'acteur : `api_tokens` pour les utilisateurs et la table personnalisée `api_clients` pour les agents industriels. Aucun second système d'authentification Edge n'a été créé.

## 2. Users

- Routes globales `users.create` et `users.store`, protégées par authentification web et vérification d'e-mail.
- Form Request avec normalisation et unicité de l'e-mail.
- Création transactionnelle, hash automatique du mot de passe, rôle plateforme contrôlé et audit sans mot de passe.
- `platform_admin` ne peut pas attribuer `super_admin`; les rôles tenant restent dans Members.

## 3. API Clients

- Migration additive : scopes JSON, expiration, révocation horodatée et dernière IP d'utilisation.
- Scopes réels limités à `camera:heartbeat` et `camera:read`.
- Statuts calculés : active, expired, revoked et disabled.
- Rotation immédiatement invalidante; aucun secret brut transmis à l'audit.

## 4. Authentification Edge Agent

- Bearer au format `<client_id>.<secret>`, permettant une recherche indexée avant comparaison constante du hash SHA-256.
- Vérification du statut, de l'expiration, de la révocation, de la factory active, des IP autorisées et du scope.
- Mise à jour de `last_used_at` et `last_used_ip`.

Les anciens secrets restent utilisables en les associant au `client_id` déjà affiché; le secret stocké n'est pas modifié.

## 5. Heartbeat Camera

- `POST /api/edge/cameras/{camera}/heartbeat`.
- Validation stricte, limitation de débit, scope `camera:heartbeat` et isolation tenant.
- Mise à jour idempotente de l'état courant.
- Historique créé seulement au premier signal ou lors d'un changement de statut.

## 6. Seuil offline et Camera Health

- Valeur globale `CAMERA_OFFLINE_THRESHOLD_SECONDS` (120 secondes par défaut).
- Surcharge par factory `camera_offline_threshold_seconds` (30 à 3600 secondes).
- Calcul centralisé : unknown, online, degraded, offline, disabled.
- La liste Camera Health affiche l'état calculé et les métriques courantes.

## 7. Profile

- Migration additive : `avatar_path`, `timezone`, `preferences`.
- Avatar JPG/PNG/WebP de 2 Mo maximum sur le disque Laravel public, nom généré et ancien fichier supprimé.
- Timezone validée par PHP.
- Préférences strictes : thème, langue, sidebar compacte et notifications e-mail.
- Rôle et permissions absents des données persistables.

## 8. Migrations, routes, middleware et services

- Migration ajoutée : `2026_07_29_000001_complete_phase_two_modules`.
- 114 routes chargées.
- Middleware Edge dédié et contrôle de scope.
- Services ajoutés pour le statut caméra et la création globale transactionnelle.

## 9. Vues et traductions

- Formulaires Users, API Clients, Profile et Settings complétés.
- Camera Health relié aux données courantes.
- Clés métier FR/EN ajoutées.

## 10. Tests et résultats

- `php artisan test` : **33 tests, 133 assertions, tous réussis**.
- Couverture ajoutée : création Users, escalade, audit, hash, auth Edge, scopes, expiration, révocation, rotation, tenant, heartbeat, statut, seuil factory, avatar, timezone et préférences.
- `php artisan route:list` : **114 routes**.
- `npm install` : réussi avec avertissement `allow-scripts` pour esbuild.
- `npm run build` : réussi, **55 modules transformés**.
- `vendor/bin/pint` : exécuté.
- `composer install --no-interaction --prefer-dist` : dépendances conformes au lock et autoload généré, mais le processus n'a pas rendu la main avant la limite de 120 secondes lors des scripts post-autoload.
- Aucun script PHPStan ou lint npm n'est configuré.

## 11. Points restants

- Appliquer la migration additive dans l'environnement ciblé après sauvegarde : elle est actuellement `Pending`.
- Effectuer la recette responsive réelle multi-navigateurs avec `RESPONSIVE_MANUAL_CHECKLIST.md`.
- Investiguer le script Composer post-autoload qui ne rend pas la main sous le compte sandbox.

## 12. Recommandations Phase 3

- Ajouter des métriques/alertes sur les refus d'authentification Edge sans journaliser les tokens.
- Ajouter un nettoyage planifié des historiques Camera Health selon la rétention.
- Étendre les scopes uniquement lors de l'ajout d'endpoints Edge réels.
