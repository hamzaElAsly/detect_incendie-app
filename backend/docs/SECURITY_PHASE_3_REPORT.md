# Rapport de sécurité — Phase 3

Date : 29 juillet 2026

## 1. Résumé exécutif

La Phase 3 est terminée techniquement. Aucun risque Critical ou High ne reste ouvert. Trois vulnérabilités High et neuf Medium ont été corrigées. Les validations dépendant de l'infrastructure sont isolées dans `SECURITY_MANUAL_CHECKLIST.md`.

## 2. Périmètre audité

Authentification web/API, sessions, CSRF, RBAC, six Policies, 42 permissions, 115 routes, multi-tenancy, binding, IDOR, SQL, validation, mass assignment, XSS, uploads, stockage, secrets, logs, audit métier, rate limiting, headers, CORS, CSP, dépendances Composer/npm et configuration de production.

## 3. Méthodologie

- Inventaire `route:list`, seeder RBAC, Policies, Gates, middleware et Form Requests.
- Cartographie de chaque modèle `factory_id` et de sa source de contexte.
- Recherche statique des requêtes raw, sinks XSS, fonctions de debug, logs de payload, fichiers secrets et opérations Storage.
- Tests adversariaux avec deux tenants, rôles distincts, payloads falsifiés, fichiers hostiles et abus répétés.
- Validation complète Laravel, migrations, routes, formatage ciblé et build Vite.

## 4. Architecture de sécurité existante

- Sessions Laravel et CSRF pour Blade.
- `api_tokens` hashés pour les utilisateurs API.
- `api_clients` hashés, expirables, révocables et scopés pour Edge.
- `FactoryContext` issu de la session autorisée ou du header API vérifié.
- RBAC relationnel, Policies/Gates, Form Requests et contrôles métier transactionnels.
- Audit logs tenant-scoped avec redaction des clés sensibles.

## 5. Matrice des rôles et permissions

Huit rôles et 42 permissions ont été vérifiés : `super_admin`, `platform_admin`, `factory_owner`, `factory_manager`, `safety_officer`, `security_agent`, `maintenance_agent`, `viewer`. La matrice route/action et la carte tenant sont dans `SECURITY_PERMISSION_MATRIX.md`.

## 6. Vulnérabilités détectées

| ID | Sévérité | Vulnérabilité | Module | Impact | Correction | Statut |
|---|---|---|---|---|---|---|
| SEC-01 | High | Session d'un utilisateur désactivé encore utilisable sur les routes globales | Auth web | Accès prolongé | Contrôle central actif, logout conservé | Corrigé |
| SEC-02 | High | Validation avant ownership tenant pour plusieurs mutations | CRUD tenant | Oracle de validation/IDOR en profondeur | Ownership dans `authorize()` | Corrigé |
| SEC-03 | High | Upload incident autorisé à tout membre de factory | Media API | Remplacement de preuve par viewer | Permission `events.create` + tenant | Corrigé |
| SEC-04 | Medium | Champs Heartbeat inconnus ignorés | Edge | Sondes de mass assignment ambiguës | Rejet 422 strict | Corrigé |
| SEC-05 | Medium | Actions sensibles sans limites dédiées | Web/API | Abus/DoS | Limiteurs par usage | Corrigé |
| SEC-06 | Medium | En-têtes défensifs incomplets | HTTP | Clickjacking/MIME sniffing | Middleware Web/API | Corrigé |
| SEC-07 | Medium | Injection de formule dans CSV | Reports | Exécution tableur | Neutralisation `= + - @` | Corrigé |
| SEC-08 | Medium | Cycle de remplacement média laissant l'ancien fichier | Storage | Rétention indésirable | Suppression post-commit | Corrigé |
| SEC-09 | Medium | Livraison média privée non tenant-scoped dans la vue | Media web | Contrôle d'accès incomplet | Route de réponse autorisée | Corrigé |
| SEC-10 | Medium | Settings API sans permission métier explicite | Settings API | Modification par viewer | `settings.view/update` | Corrigé |
| SEC-11 | Medium | Changements Settings non audités | Audit | Traçabilité manquante | Audit old/new Web et API | Corrigé |
| SEC-12 | Medium | CORS non explicite et CSP absente | HTTP | Politique dépendante des defaults | Allowlist CORS + CSP Report-Only | Corrigé |
| SEC-13 | Low | Variables cookies/CORS non documentées | Configuration | Déploiement fragile | `.env.example` complété | Corrigé |

### Secrets, logs et artefacts sensibles

| Type | Emplacement approximatif | Constat | Action |
|---|---|---|---|
| Environnement local | racine, `.env` | fichier ignoré par Git; aucune valeur reproduite dans ce rapport | exclure des artefacts et gérer les valeurs en coffre |
| Valeurs de démonstration | seeders et exemples de configuration | réservées au développement | remplacer/désactiver avant production |
| Clés, certificats, dumps | dépôt backend audité | aucun fichier de clé/certificat ou dump sensible identifié | maintenir les règles d'exclusion et le scan CI |
| Logs applicatifs | appels de journalisation et `storage/logs` | aucun bearer token, secret ou payload complet journalisé; logs locaux ignorés | centraliser avec accès restreint et redaction |

## 7. Corrections d’autorisations

Les utilisateurs désactivés sont bloqués sur les routes globales et tenant. Les ressources Zone, Camera, Recipient et Member vérifient leur factory avant validation. Les Settings API requièrent désormais leurs permissions. L'upload incident requiert `events.create`. Les rôles plateforme restent immuables et l'escalade PA→SA est refusée.

## 8. Corrections multi-tenant

Les index partent du contexte actif. Les détails, modifications, suppressions, exports et médias comparent la factory liée. Les IDs imbriqués sont contraints à la factory. La factory du Heartbeat vient du client API, jamais du payload. Une route dédiée sert les médias privés seulement après autorisation.

## 9. Corrections API Client et Edge Agent

Token `<client_id>.<secret>`, lookup indexé, comparaison constante, refus uniforme 401, statut/expiration/révocation/IP/scope vérifiés. L'ancien secret cesse de fonctionner après rotation. Le Heartbeat refuse les timestamps périmés, valeurs hors limites, champs supplémentaires et accès cross-tenant; limite de 120/min/client.

## 10. Corrections validation et mass assignment

Les services sélectionnent explicitement les champs sensibles. `factory_id`, `role`, `permissions`, `is_super_admin`, `secret_hash`, `revoked_at` et `last_used_at` ne sont pas alimentés par des payloads non autorisés. Profile impose une allowlist JSON. Les relations Zone et User sont tenant-scoped.

## 11. Corrections XSS et sorties

Aucun `{!! !!}`, `x-html`, `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `document.write`, `eval` ou `new Function` n'est conservé. Blade échappe les contenus Factory, Camera, Zone, Alert, audit et erreur Edge. Les tests stored-XSS et recherche confirment l'échappement contextuel.

## 12. Corrections uploads

- Avatar : image réelle JPG/PNG/WebP, 2 Mo, SVG/script refusé, nom Storage généré.
- Incident : image JPG/PNG/WebP 10 Mo ou vidéo MP4/WebM/MOV 50 Mo, MIME vérifié, ULID, chemin serveur construit.
- Disque incident privé par défaut.
- Remplacement supprimant l'ancien fichier après persistance réussie.
- Téléchargement/affichage via route tenant-scoped; aucun chemin client accepté.

## 13. Rate limiting

Login 10/min par IP+email; Edge 120/min par client; écritures sensibles 20/min; gestion de secrets, upload profil et exports 10/min. Le 121e Heartbeat est testé à 429.

## 14. Sessions, CSRF et cookies

Login régénère la session; logout l'invalide et régénère le token CSRF. Les mutations Blade utilisent le middleware web et `@csrf`. `.env.example` documente Secure, HttpOnly et SameSite. Les valeurs de production restent une validation manuelle.

## 15. Headers et CORS

`nosniff`, `DENY`, Referrer-Policy, Permissions-Policy sont actifs sur Web/API. HSTS est ajouté seulement sous HTTPS. Une CSP Report-Only compatible avec l'existant est émise sans prétendre à l'enforcement. CORS limite `api/*`, origines configurées, méthodes/headers connus, sans credentials.

## 16. Audit des dépendances

`composer audit --locked` et `npm audit --audit-level=moderate` ont été exécutés mais les registres Packagist/npm sont injoignables depuis le sandbox. Aucun résultat de sûreté n'est revendiqué. Ces deux contrôles restent obligatoires en CI connectée. Aucune mise à jour majeure n'a été appliquée.

`composer install --no-interaction --prefer-dist` et `npm install` réussissent avec les verrous présents. npm signale seulement que le script postinstall de `esbuild@0.28.1` doit être explicitement approuvé selon la politique locale `allow-scripts`.

## 17. Tests ajoutés

- `SecurityHardeningTest` : auth, headers/CSP/CORS, Edge, XSS, SQL payload, escalade, mass assignment, settings/audit.
- `TenantIsolationTest` : deux tenants, index, binding, GET/PUT/DELETE, factory et nested ID falsifiés.
- `FileUploadSecurityTest` : permission média, cross-tenant, MIME falsifié, taille, traversal, remplacement et accès privé.
- Tests Phase 2 conservés pour expiration, révocation, rotation, scopes, avatar et rôles.

## 18. Résultats des tests

`php artisan test` réussit : **52 tests, 345 assertions**. Les trois suites dédiées sous `tests/Feature/Security` couvrent notamment isolation tenant, IDOR, XSS, injection SQL, escalade de privilèges, Edge, CORS, headers, audit Settings et uploads. Aucune assertion existante n'a été retirée.

## 19. Résultat du build

`npm run build` réussit avec Vite 7.3.6 : **55 modules transformés**. Les assets Tailwind/Alpine compilent sans changement de technologie.

## 20. Risques résiduels

- Audits de dépendances distants non concluants sans réseau.
- CSP encore Report-Only à cause des styles/scripts inline historiques.
- Scanner antivirus vidéo non intégré; décision d'infrastructure requise.
- Logs centralisés, reverse proxy et permissions système non vérifiables localement.

## 21. Actions manuelles nécessaires

Exécuter `SECURITY_MANUAL_CHECKLIST.md` : HTTPS/HSTS, cookies Secure, CORS réel, rapports CSP, permissions Storage, gestionnaire de secrets, sauvegarde/restauration, audits CI et recette navigateur.

## 22. Fichiers modifiés

Principaux ajouts/modifications Phase 3 : middleware headers, configuration CORS, requêtes tenant, contrôleurs/services Media et Settings, route média sécurisée, `.env.example`, vues Event, tests `tests/Feature/Security`, matrice, rapport, checklist et `etat_avancement.md`.

## 23. Recommandations Phase 4

- Ajouter antivirus/inspection asynchrone des médias avant consommation IA.
- Migrer les scripts/styles inline vers nonce/hash et activer CSP enforce.
- Centraliser les événements de refus Edge répétés dans le SIEM sans token.
- Exécuter les audits de dépendances à chaque pipeline et bloquer les Critical/High.
