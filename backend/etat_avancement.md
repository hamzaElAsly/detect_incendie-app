# Etat d'avancement - Detect Incendie On-Premise

*Derniere mise a jour : 4 septembre 2026*

## Vue d'ensemble

**Detect Incendie** est maintenant oriente comme plateforme on-premise mono-site pour la detection IA et la supervision locale des risques d'incendie dans une usine ou un site industriel.

```text
Caméras → Python Edge Agent → YOLO → Laravel API → MySQL → Fire Command Center
```

## Progression

| Module | Progression | Statut |
|---|---:|---|
| Architecture Laravel / backend | 82 % | Avancé |
| Dashboard Blade | 90 % | Avancé |
| Frontend | 90 % | Recette visuelle à finaliser |
| Modules métier | 75 % | En cours |
| Rôles et permissions | 85 % | Avancé |
| Dataset IA | 90 % | Presque terminé |
| Entraînement YOLO | 20 % | À réaliser |
| Edge Agent Python | 25 % | Début |
| Notifications temps réel | 35 % | Partiel |
| Tests | 85 % | 52 tests / 345 assertions |
| Déploiement Production | 10 % | Non commencé |

## Progression globale

**Estimation : 70 % a 75 %.**

Le socle Laravel est refactorise vers une installation locale mono-site. Les priorites restantes concernent la validation sur environnement cible, la validation RTSP reelle, l'integration IA metier et le deploiement local.

## Phase A - SaaS vers On-Premise Refactor

**Statut : Termine cote application Laravel - migration cible et validations terrain restantes**

- [x] SaaS Plans retires des routes, navigation, permissions et flux applicatifs
- [x] SaaS Subscriptions retirees des routes, navigation, permissions et flux applicatifs
- [x] Tenant/factory switching retire de l'usage normal
- [x] Gestion multi-factory client retiree du web UI
- [x] Modele single local factory etabli avec `Factory::local()`
- [x] Factory Settings disponible via `/settings/factory`
- [x] Roles SaaS migres/refactorises vers `system_admin` et `factory_admin`
- [x] Permissions SaaS nettoyees dans le seeder et la migration Phase A
- [x] Sidebar/navigation adaptee au produit on-premise
- [x] Dashboard verifie sans metriques SaaS
- [x] Users et members fonctionnent localement
- [x] Cameras conservees et protegees par permissions
- [x] Alerts conservees et scopees au site local
- [x] Detections et historique conserves
- [x] Camera Health conserve
- [x] Alert Recipients conserve
- [x] API Clients conserve pour Edge Agent et integrations locales
- [x] Contrat Edge Agent conserve pour heartbeat, detection, idempotence et snapshots
- [x] Securite Phase 3 preservee par tests automatises
- [x] Audit offline-first effectue; dependances cloud runtime Laravel non trouvees
- [x] Tables legacy `plans` et `subscriptions` conservees volontairement pour eviter un drop destructif
- [x] Suite Laravel complete: 55 tests, 379 assertions
- [x] Build frontend Vite: reussi, 55 modules
- [x] Routes validees: 106 routes, aucune route plans/subscriptions/billing/tenant
- [x] Documentation creee: `PHASE_A_ON_PREMISE_REFACTOR.md`
- [ ] Migration Phase A a appliquer sur la base cible apres sauvegarde
- [ ] Recette navigateur manuelle du sidebar/dashboard
- [ ] Test offline reel sur environnement de deploiement
- [ ] Validation camera RTSP reelle

### Decisions Phase A

- Le modele `Factory` et les colonnes `factory_id` sont conserves pour proteger les donnees historiques et les controles d'autorisation.
- Les anciennes routes API `/api/factories/{factory}/...` restent comme aliases de compatibilite et refusent tout ID qui ne correspond pas au site local.
- Les nouvelles routes API privilegiees pour l'on-premise sont sous `/api/installation/...`.
- Les tables historiques `plans` et `subscriptions` ne sont plus utilisees par l'application mais restent dans le schema pour une decision de nettoyage ulterieure.

### Validation Phase A - 4 septembre 2026

- `php artisan test` : reussi, 55 tests, 379 assertions
- `npm.cmd run build` : reussi, 55 modules transformes
- `php artisan route:list` : 106 routes chargees
- `php artisan route:list --path=plans` : aucune route
- `php artisan route:list --path=subscriptions` : aucune route
- `php artisan route:list --path=billing` : aucune route
- `php artisan route:list --path=tenant` : aucune route
- `php artisan migrate:status` : migration Phase A en attente sur la base locale configuree
- `composer validate --no-check-publish` : `composer.json` valide; avertissements git dubious ownership dus au sandbox
- `vendor/bin/pint --dirty` : reussi

## Réalisations principales

### Backend

- Authentification et vérification d'email
- Contexte local mono-site et protections IDOR
- Factories, Members, Zones et Cameras
- Fire Events, Alerts et Notifications
- Reports et Audit Logs
- API Clients avec secrets hashés, rotation et révocation
- Plans et Subscriptions retires des flux applicatifs en Phase A; tables legacy conservees
- RBAC personnalisé avec huit rôles

### Interface

- Dashboard Laravel Blade Fire Command Center
- Sidebar et topbar responsive
- Dark mode et light mode
- Pages CRUD reliées au design system partagé
- Boutons, formulaires, tableaux, modales et états vides uniformisés
- Export CSV

### Intelligence artificielle

- Dataset Fire / Smoke préparé
- Jeux Train / Validation / Test
- Nettoyage des données
- Validation des annotations

## Phase 1 — Finalisation du Frontend

**Statut : En cours**

- [x] Thème Fire Command Center finalisé techniquement
- [x] Layout principal uniformisé
- [x] Sidebar finalisée
- [x] Topbar finalisée
- [x] Dashboard uniformisé
- [x] Pages CRUD reliées au design system partagé
- [x] Boutons adaptés
- [x] Formulaires uniformisés
- [x] Tableaux uniformisés
- [x] Modales de confirmation uniformisées
- [x] Dark/Light mode vérifié par build et revue statique
- [ ] Responsive mobile vérifié manuellement sur navigateur
- [ ] Responsive tablette vérifié manuellement sur navigateur
- [ ] Responsive desktop vérifié manuellement sur navigateur
- [x] Build frontend réussi — 55 modules
- [x] Tests réussis — 20 tests, 89 assertions lors de la Phase 1

### Travaux réalisés

- Tokens Fire Command Center centralisés pour les thèmes light/dark
- Sidebar sombre fixe, compacte et responsive
- API boutons avec huit variantes, quatre tailles et état loading
- Formulaires, tableaux, pagination et états vides harmonisés
- Dashboard enrichi avec événements récents, santé système et activité
- Confirmations destructrices accessibles et centralisées
- Login et vérification e-mail uniformisés
- Erreurs 403, 404, 419, 500 et 503 ajoutées
- Accessibilité clavier, focus et reduced-motion renforcés
- 40 pages Blade revues ; routes, permissions et logique métier préservées

### Points restants après la Phase 1

- Effectuer une recette visuelle manuelle multi-navigateurs aux neuf largeurs cibles
- Migrer les libellés métier historiques encore codés en français vers les traductions FR/EN
- Ne pas créer Forgot Password, Reset Password ou 2FA avant que les routes web correspondantes existent

## Phase 2 — Compléter les modules

**Statut : En cours**

- [x] Factories
- [x] Members
- [x] Users
- [x] Roles & Permissions
- [x] Camera Health
- [x] Alert Recipients
- [x] API Clients
- [x] Plans legacy retires des flux Phase A
- [x] Subscriptions legacy retirees des flux Phase A
- [x] Profile

### Factories

- [x] CRUD, Form Request et Policy vérifiés
- [x] Autorisations et accès factory vérifiés
- [x] Audit transactionnel ajouté
- [x] Profil du site local correctement relie

### Members

- [x] Invitation d'un nouvel utilisateur
- [x] Ajout d'un utilisateur existant sans doublon
- [x] Protection du dernier factory owner
- [x] Prévention de l'escalade de rôle
- [x] Ancienne limite de plan rendue obsolete par le modele mono-site
- [x] Audit et tests ajoutés

### Users

- [x] Liste, détail, édition et désactivation protégés
- [x] Auto-désactivation et dernier super-admin protégés
- [x] Audit ajouté
- [x] Creation systeme transactionnelle et attribution de roles securisee

### Roles & Permissions

- [x] Huit rôles et matrice existante vérifiés
- [x] Seeder idempotent conservé
- [x] Rôles système rendus immuables
- [x] Modifications de permissions auditées

### Camera Health

- [x] Liste, filtres, historique et isolation site local verifies
- [x] Endpoint heartbeat Edge Agent et limitation de débit
- [x] Authentification API Client, scope et isolation site local
- [x] Seuil offline global et configurable par factory

### Alert Recipients

- [x] CRUD et validation vérifiés
- [x] Appartenance à la factory validée
- [x] Audit et tests d'isolation site local ajoutes

### API Clients

- [x] Secret à affichage unique et stockage hashé
- [x] Rotation, révocation et audit
- [x] Expiration, scopes et dernière utilisation ajoutés
- [x] Authentification Edge Agent testée

### Plans legacy retires en Phase A

- [x] Routes, navigation et permissions retirees des flux applicatifs
- [x] Tables conservees pour nettoyage ulterieur apres audit des donnees
- [x] Aucune limite SaaS active conservee dans l'usage normal

### Subscriptions legacy retirees en Phase A

- [x] Routes, navigation et permissions retirees des flux applicatifs
- [x] Tables conservees pour nettoyage ulterieur apres audit des donnees
- [x] Aucun flux abonnement/trial actif dans l'usage normal

### Profile

- [x] Nom, email, téléphone et mot de passe sécurisés
- [x] Revalidation email conservée
- [x] Modification de rôle impossible depuis le profil
- [x] Avatar sécurisé, timezone et préférences strictes

### **Statut : Terminée techniquement — migration en attente d’application sur l’environnement cible**

- [x] Factories
- [x] Members
- [x] Users
- [x] Roles & Permissions
- [x] Camera Health
- [x] Alert Recipients
- [x] API Clients
- [x] Plans legacy retires des flux Phase A
- [x] Subscriptions legacy retirees des flux Phase A
- [x] Profile

### Validation de la Phase 2

- `composer install --no-interaction --prefer-dist` : réussi
- `npm.cmd install` : réussi avec avertissement postinstall `esbuild`
- `php artisan optimize:clear` : réussi
- `php artisan migrate:status` : 5 migrations appliquées, 1 migration additive en attente
- `php artisan route:list` : 114 routes chargées
- `php artisan test` : 33 tests réussis, 133 assertions
- `npm.cmd run build` : réussi, 55 modules transformés
- `vendor/bin/pint` : exécuté ; aucun reformatage hors périmètre conservé

### Points restants après la Phase 2

- Appliquer la migration additive sur l'environnement cible après sauvegarde
- Effectuer la recette responsive manuelle multi-navigateurs (non cochée)
- Diagnostiquer le script Composer post-autoload qui dépasse 120 secondes sous le sandbox

### Résumé de finalisation du 29 juillet 2026

- Users : creation systeme, role controle, transaction, audit et tests.
- API Clients : scopes, expiration, révocation horodatée, authentification Edge et dernière utilisation.
- Camera Health : heartbeat scope au site local, statut centralise, seuil configurable et metriques courantes.
- Profile : avatar Storage, timezone PHP, préférences strictes et audit.
- Migration ajoutée : `2026_07_29_000001_complete_phase_two_modules`.
- Build Vite réussi avec 55 modules ; 33 tests et 133 assertions réussis.
- Checklist manuelle créée dans `RESPONSIVE_MANUAL_CHECKLIST.md` sans fausse validation navigateur.

## Phase 3 — Sécurité

**Statut : Terminée techniquement — validations d’infrastructure manuelles restantes**

- [x] Audit complet des permissions
- [x] Renforcement de l'isolation des données
- [x] Tests de sécurité complémentaires

### Validation Phase 3 — 29 juillet 2026

- Matrice complète : `SECURITY_PERMISSION_MATRIX.md`
- Rapport OWASP et risques résiduels : `SECURITY_PHASE_3_REPORT.md`
- Utilisateurs desactives bloques sur les routes locales et systeme
- Autorisation site local executee avant validation des mutations
- Heartbeat strict, protégé contre les champs inattendus et limité à 120/min/client
- Actions sensibles limitées par usage
- En-têtes HTTP défensifs ajoutés
- CORS explicite et CSP Report-Only ajoutés
- Injection de formules CSV neutralisée
- Uploads incidents prives, autorises, randomises et servis par une route scopee au site local
- Changements Settings protégés par permission et journalisés
- Stored XSS et escalades de privilèges couverts par tests
- `php artisan test` : 52 tests réussis, 345 assertions
- `php artisan route:list` : 115 routes
- `npm run build` : réussi, 55 modules
- Migration additive Phase 2 : appliquée (batch 2)
- Migrations Phase 3 créées : 0 ; migrations en attente : 0
- Vulnérabilités : 0 Critical, 3 High corrigées, 9 Medium corrigées, 1 Low corrigée, aucune Critical/High ouverte
- Checklist manuelle : 33 contrôles d'infrastructure restant à valider
- Audits Composer/npm non concluants dans le sandbox sans accès réseau ; à exécuter en CI

## Phase 4 — Intelligence artificielle

**Statut : Infrastructure IA prête — entraînement réel en attente**

- [ ] Entraîner YOLO
- [ ] Évaluer les performances
- [ ] Intégrer le modèle

### Validation Phase 4 — 23 août 2026

- Dataset trouvé : 20 309 images, 20 309 labels, classes `fire` et `smoke`; dérivé propre utilisé pour l'entraînement après sauvegarde de 70 JPEG originaux non réinscriptibles
- Splits présents : 14 411 train, 2 812 validation, 3 086 test
- Validation courante : 0 annotation invalide, 0 fichier non apparié, 0 image corrompue dans le dérivé; mapping `0=fire`, `1=smoke`
- Environnement : Python 3.12.10 dans `.venv`, PyTorch 2.13.0 CPU, Torchvision 0.28.0 CPU, Ultralytics 8.4.95, CUDA absent
- Poids génériques YOLO11 présents; aucun `best.pt`/`last.pt` spécialisé
- Smoke test YOLO11s CPU démarré mais interrompu avant la fin de la première epoch après observation de 2/3597 batches; aucune métrique ni poids produits
- Entraînement complet et évaluation indépendante non exécutés; le CPU observé projette plusieurs dizaines d'heures pour un smoke complet et ne justifie pas un lancement automatique de 100 epochs
- Garde d'entraînement active : le pipeline refuse tout Full Training sans `reports/SMOKE_TRAINING_PASS.json` attestant un smoke réussi
- Edge Agent Laravel préparé avec filtrage temporel et file offline
- API Laravel de detection integree avec scope site local, idempotence, audit et snapshot prive
- Tests rapides Edge : 6 réussis via `unittest`; pytest absent
- Tests Laravel ciblés : 6 réussis, 34 assertions
- Suite Laravel complète : 58 tests réussis, 379 assertions
- `php artisan route:list` : 116 routes
- Build Vite réussi : 55 modules transformés
- Migrations en attente : 0
- Intégration réelle modèle/caméra/API non validée; case laissée décochée

## Phase 5 — Edge Agent

- [x] Flux RTSP/webcam/fichier implémenté et couvert par tests simulés
- [x] Heartbeat Laravel non bloquant implémenté
- [x] Pipeline de détection et idempotence implémentés
- [x] Upload multipart et cycle de vie Snapshot implémentés
- [x] Buffer local SQLite WAL avec reprise après crash implémenté
- [x] Reconnexion avec backoff et jitter implémentée
- [ ] Validation sur caméra RTSP réelle
- [ ] Validation caméra → Edge → API sur environnement réel
- [ ] Validation métier avec modèle spécialisé `best.pt`

### Validation technique Phase 5 — 4 août 2026

- 50 tests Python réussis via `unittest`, incluant reconnexion, reprise offline, multipart HTTP local et pipeline dry-run.
- 58 tests Laravel reussis, 379 assertions; isolation site local et authentification inchangées.
- Aucun modèle spécialisé ni flux caméra réel disponible : aucune validation réelle ou métier n’est revendiquée.
- Rapport détaillé : `PHASE_5_EDGE_AGENT_REPORT.md`.

## Phase 6 — Dashboard temps réel

- [ ] Live Camera Grid temps réel
- [ ] Carte des zones
- [ ] Statistiques temps réel
- [ ] Alertes temps réel

## Phase 7 — Notifications

- [ ] Email
- [ ] SMS
- [ ] WhatsApp
- [ ] Push

## Phase 8 — Tests et déploiement

- [ ] Tests complets
- [ ] Docker
- [ ] Redis
- [ ] CI/CD
- [ ] Monitoring

## Prochaine étape recommandée

Appliquer la migration Phase A sur la base cible apres sauvegarde, puis effectuer la recette locale navigateur, offline et Edge Agent sans revendiquer de validation RTSP reelle.

## Objectif

Construire une plateforme on-premise professionnelle de detection d'incendie en temps reel pour un site industriel local, combinant IA, supervision, alertes et exploitation sans dependance Internet pour le coeur du command center.
