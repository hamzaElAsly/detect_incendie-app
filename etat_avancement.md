# État d'avancement --- Plateforme Detect Incendie SaaS

*Date : Juillet 2026*

## Vue d'ensemble

La plateforme **Detect Incendie** est un SaaS de détection d'incendie
destiné aux usines et sites industriels.

Architecture cible :

``` text
Caméras
    ↓
Python Edge Agent
    ↓
YOLO (Fire / Smoke Detection)
    ↓
Laravel API
    ↓
MySQL
    ↓
Dashboard Fire Command Center
```

## État d'avancement

  Module                       Progression Statut
  -------------------------- ------------- --------------------
  Architecture Laravel                75 % 🟢 Avancé
  Dashboard Blade                     70 % 🟢 En cours
  CRUD métier                         70 % 🟢 En cours
  Rôles & Permissions                 65 % 🟡 À finaliser
  Dataset IA                          90 % 🟢 Presque terminé
  Entraînement YOLO                   20 % 🔴 À réaliser
  Edge Agent Python                   25 % 🔴 Début
  Notifications temps réel            35 % 🟡 Partiel
  Tests                               30 % 🟡 À renforcer
  Déploiement Production              10 % 🔴 Non commencé

## Progression globale

**Estimation : 55 % à 60 %.**

Le backend Laravel et le Dashboard sont bien avancés. La priorité est
désormais l'intégration IA, l'Edge Agent, les tests et le déploiement.

## Réalisations

### Backend

-   Authentification
-   Multi-tenancy
-   Factories
-   Zones
-   Cameras
-   Fire Events
-   Alerts
-   Notifications
-   Reports
-   Audit Logs
-   API Clients
-   Plans & Subscriptions
-   Rôles & Permissions

### Interface

-   Dashboard Laravel Blade
-   Sidebar
-   Topbar
-   Dark / Light Mode
-   Fire Command Center (en cours)
-   CRUD principaux
-   Export CSV

### Intelligence Artificielle

-   Dataset Fire / Smoke préparé
-   Jeux Train / Validation / Test
-   Nettoyage des données
-   Validation des annotations

## Roadmap

### Phase 1 --- Finaliser le Frontend

-   Finaliser le thème Fire Command Center
-   Uniformiser toutes les pages
-   Optimiser le responsive

### Phase 2 --- Compléter les modules

-   Factories
-   Members
-   Users
-   Roles & Permissions
-   Camera Health
-   Alert Recipients
-   API Clients
-   Plans
-   Subscriptions
-   Profile

### Phase 3 --- Sécurité

-   Vérifier les permissions
-   Isolation des données
-   Tests de sécurité

### Phase 4 --- Intelligence Artificielle

-   Entraîner YOLO
-   Évaluer les performances
-   Intégrer le modèle

### Phase 5 --- Edge Agent

-   Flux RTSP
-   Heartbeat
-   Détections
-   Upload Snapshot
-   Buffer local
-   Reconnexion

### Phase 6 --- Dashboard Temps Réel

-   Live Camera Grid
-   Carte des zones
-   Statistiques
-   Alertes

### Phase 7 --- Notifications

-   Email
-   SMS
-   WhatsApp
-   Push

### Phase 8 --- Tests & Déploiement

-   Tests complets
-   Docker
-   Redis
-   CI/CD
-   Monitoring

## Objectif

Construire une plateforme SaaS professionnelle de détection d'incendie
en temps réel pour les sites industriels, combinant IA, supervision et
alertes automatiques.