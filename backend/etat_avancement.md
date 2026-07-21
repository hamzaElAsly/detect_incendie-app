# État d'avancement — Plateforme Detect Incendie SaaS

*Dernière mise à jour : 21 juillet 2026*

## Vue d'ensemble

**Detect Incendie** est une plateforme SaaS multi-tenant destinée à la détection et à la supervision des risques d'incendie dans les sites industriels.

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
| Tests | 55 % | 27 tests / 103 assertions |
| Déploiement Production | 10 % | Non commencé |

## Progression globale

**Estimation : 65 % à 70 %.**

Le frontend technique et six modules de la Phase 2 sont finalisés dans le périmètre actuellement supporté. Les priorités restantes concernent Camera Health, l'authentification Edge Agent, les capacités avancées des API Clients, l'intégration IA et le déploiement.

## Réalisations principales

### Backend

- Authentification et vérification d'email
- Multi-tenancy et protections IDOR
- Factories, Members, Zones et Cameras
- Fire Events, Alerts et Notifications
- Reports et Audit Logs
- API Clients avec secrets hashés, rotation et révocation
- Plans et Subscriptions avec limites membres/caméras
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
- [ ] Users
- [x] Roles & Permissions
- [ ] Camera Health
- [x] Alert Recipients
- [ ] API Clients
- [x] Plans
- [x] Subscriptions
- [ ] Profile

### Factories

- [x] CRUD, Form Request et Policy vérifiés
- [x] Autorisations et accès factory vérifiés
- [x] Audit transactionnel ajouté
- [x] Abonnement courant correctement relié

### Members

- [x] Invitation d'un nouvel utilisateur
- [x] Ajout d'un utilisateur existant sans doublon
- [x] Protection du dernier factory owner
- [x] Prévention de l'escalade de rôle
- [x] Limite du plan appliquée
- [x] Audit et tests ajoutés

### Users

- [x] Liste, détail, édition et désactivation protégés
- [x] Auto-désactivation et dernier super-admin protégés
- [x] Audit ajouté
- [ ] Création globale et attribution de rôles absentes des routes actuelles

### Roles & Permissions

- [x] Huit rôles et matrice existante vérifiés
- [x] Seeder idempotent conservé
- [x] Rôles système rendus immuables
- [x] Modifications de permissions auditées

### Camera Health

- [x] Liste, filtres, historique et isolation tenant vérifiés
- [ ] Endpoint heartbeat Edge Agent absent
- [ ] Authentification caméra/API Client absente
- [ ] Seuil offline configurable absent

### Alert Recipients

- [x] CRUD et validation vérifiés
- [x] Appartenance à la factory validée
- [x] Audit et tests tenant ajoutés

### API Clients

- [x] Secret à affichage unique et stockage hashé
- [x] Rotation, révocation et audit
- [ ] Expiration et scopes absents du schéma
- [ ] Authentification Edge Agent par API Client absente

### Plans

- [x] Gestion plateforme et audit
- [x] Limites membres et caméras appliquées
- [x] Aucune limite fictive ajoutée

### Subscriptions

- [x] Correction de la création tenant
- [x] Unicité de l'abonnement actif/trial
- [x] Transactions, audit et tests ajoutés

### Profile

- [x] Nom, email, téléphone et mot de passe sécurisés
- [x] Revalidation email conservée
- [x] Modification de rôle impossible depuis le profil
- [ ] Avatar, timezone et préférences absents du schéma

### Validation de la Phase 2

- `composer install --no-interaction --prefer-dist` : réussi
- `npm.cmd install` : réussi avec avertissement postinstall `esbuild`
- `php artisan optimize:clear` : réussi
- `php artisan migrate:status` : 5 migrations appliquées
- `php artisan route:list` : 111 routes chargées
- `php artisan test` : 27 tests réussis, 103 assertions
- `npm.cmd run build` : réussi, 55 modules transformés
- `vendor/bin/pint` : exécuté ; aucun reformatage hors périmètre conservé

### Points restants après la Phase 2

- Définir et implémenter le protocole heartbeat et l'authentification Edge Agent
- Étendre le schéma API Clients avec expiration et scopes
- Décider le périmètre produit du CRUD Users global
- Décider les champs persistants de préférences Profile
- Effectuer la recette responsive manuelle multi-navigateurs

## Phase 3 — Sécurité

- [ ] Audit complet des permissions
- [ ] Renforcement de l'isolation des données
- [ ] Tests de sécurité complémentaires

## Phase 4 — Intelligence artificielle

- [ ] Entraîner YOLO
- [ ] Évaluer les performances
- [ ] Intégrer le modèle

## Phase 5 — Edge Agent

- [ ] Flux RTSP
- [ ] Heartbeat
- [ ] Détections
- [ ] Upload Snapshot
- [ ] Buffer local
- [ ] Reconnexion

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

Concevoir le contrat Edge Agent sécurisé et tenant-scoped, puis l'ingestion des données Camera Health avant l'intégration IA.

## Objectif

Construire une plateforme SaaS professionnelle de détection d'incendie en temps réel pour les sites industriels, combinant IA, supervision et alertes automatiques.
