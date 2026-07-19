# Fire Detection SaaS

## Présentation

Fire Detection SaaS est une plateforme web multi-usine dédiée à la supervision des risques d'incendie. Elle centralise les caméras et les zones surveillées, enregistre les détections de feu ou de fumée, facilite le traitement des incidents et assure le suivi des alertes et des notifications.

Le backend fournit deux interfaces complémentaires :

- un dashboard web rendu côté serveur pour les administrateurs et les opérateurs ;
- une API REST destinée aux applications clientes et aux intégrations techniques.

Chaque usine constitue un tenant isolé. Un utilisateur ne peut consulter et modifier que les données des usines auxquelles il appartient, sauf lorsqu'il possède les privilèges de super-administrateur.

## Objectifs du projet

- Centraliser la surveillance de plusieurs usines depuis une même plateforme.
- Structurer les caméras par usine et par zone de risque.
- Enregistrer les détections de feu et de fumée avec leur niveau de confiance et leurs médias.
- Permettre aux opérateurs de confirmer un incident, de le déclarer comme fausse alerte ou de le résoudre.
- Alerter les destinataires configurés selon le canal et le niveau de gravité.
- Surveiller l'état de fonctionnement et les performances des caméras.
- Fournir des indicateurs, graphiques, rapports et exports utiles à la prise de décision.
- Garantir l'isolation des données, la traçabilité des actions et la protection des secrets.

## Architecture du système

L'application suit l'architecture MVC de Laravel, complétée par des services métier et un contexte multi-tenant.

```text
Caméras / clients externes
            |
            v
        API REST  <---->  Authentification par jeton
            |
            v
Contrôleurs et services métier
            |
            v
 Eloquent ORM  <---->  Base de données MySQL
            ^
            |
 Dashboard Blade  <---->  Authentification par session
```

Les principales couches sont :

- `routes/` : routes web, API d'authentification et API métier de la plateforme ;
- `app/Http/Controllers/` : contrôleurs web et API ;
- `app/Domain/` : services et cas d'usage liés au dashboard, à l'audit, aux paramètres, aux médias et aux clients API ;
- `app/Models/` : modèles Eloquent et relations métier ;
- `app/Services/FactoryContext.php` : conservation du contexte de l'usine active pendant une requête ;
- `app/Http/Middleware/` : authentification API et résolution sécurisée du tenant ;
- `resources/views/` : pages, layouts et composants Blade du dashboard ;
- `database/migrations/` et `database/seeders/` : schéma et données initiales ;
- `tests/` : tests unitaires et fonctionnels.

Le dashboard utilise un rendu serveur classique. L'API expose notamment l'authentification, le contexte d'usine, les métriques du dashboard, les paramètres, les journaux d'audit et le dépôt des médias d'incident.

## Fonctionnalités principales

- Authentification, déconnexion, rotation des jetons et réinitialisation du mot de passe.
- Vérification de l'adresse e-mail et gestion du profil utilisateur.
- Gestion multi-usine avec sélection d'une usine active.
- Gestion des utilisateurs, membres, rôles et permissions.
- Gestion des plans et des abonnements.
- Gestion des zones, caméras, seuils de détection et identifiants de flux.
- Suivi de la santé des caméras : statut, latence, FPS et utilisation des ressources.
- Gestion des événements de feu ou de fumée et de leurs détections détaillées.
- Workflow des incidents : détection, confirmation, fausse alerte et résolution.
- Ajout d'images et de vidéos aux événements.
- Création et acquittement des alertes.
- Gestion des destinataires, canaux et seuils de gravité des notifications.
- Tableau de bord avec synthèse, métriques, état des caméras, événements récents et graphiques.
- Paramètres propres à chaque usine.
- Historique d'audit, rapports et export des données.
- Gestion de clients API et régénération ou révocation de leurs secrets.
- Endpoint de santé contrôlant la base de données, la file d'attente et le stockage.

## Base de données

L'environnement fourni utilise **MySQL** (`DB_CONNECTION=mysql`) avec Eloquent ORM et les migrations Laravel. La majorité des entités métier utilisent des identifiants ULID. Les suppressions logiques sont appliquées aux zones, caméras et événements afin de préserver l'historique.

Les tables sont organisées par domaine :

| Domaine | Tables principales | Rôle |
|---|---|---|
| Utilisateurs et accès | `users`, `roles`, `permissions`, `role_user`, `permission_role`, `api_tokens` | Comptes, autorisations et jetons d'accès |
| Multi-tenant | `factories`, `factory_user`, `factory_settings` | Usines, appartenances et configuration |
| Supervision | `zones`, `cameras`, `camera_credentials`, `camera_health_logs` | Parc de caméras, flux et données de santé |
| Incidents | `fire_events`, `fire_event_detections` | Événements détectés, scores, boîtes englobantes et médias |
| Alertes | `alerts`, `alert_recipients`, `notifications`, `notification_logs` | Diffusion et suivi des alertes |
| Intégrations et audit | `api_clients`, `audit_logs` | Accès machine-à-machine et traçabilité |
| Abonnements | `plans`, `subscriptions` | Limites fonctionnelles et souscriptions |
| Infrastructure Laravel | `sessions`, `cache`, `jobs`, `failed_jobs` | Sessions, cache et traitements asynchrones |

Les relations métier portent le `factory_id` lorsque les données appartiennent à une usine. Les clés étrangères, index et contraintes d'unicité assurent l'intégrité du modèle.

## Sécurité

- Authentification web par session et authentification API par jeton Bearer.
- Jetons API stockés sous forme de hash SHA-256, révocables et éventuellement expirables.
- Limitation du débit : groupe dédié à l'authentification et groupe dédié aux routes API protégées.
- Isolation multi-tenant par middleware, contrôle d'appartenance et filtrage systématique par `factory_id`.
- Autorisations basées sur les rôles, permissions et Policies Laravel.
- Protection des routes web par les middlewares `auth`, `verified`, `web.context` et `factory.web`.
- Validation des entrées par des Form Requests dédiées.
- Protection CSRF native de Laravel pour les formulaires web.
- Hachage des mots de passe via les mécanismes Laravel.
- Chiffrement applicatif des URL de flux RTSP et des identifiants des caméras.
- Masquage des secrets dans les réponses Eloquent et affichage unique des secrets de clients API.
- Nettoyage des champs sensibles avant leur écriture dans les journaux d'audit.
- Journalisation des actions avec l'utilisateur, l'usine, l'adresse IP, le user-agent et les valeurs modifiées.
- Validation du type et de la taille des images et vidéos déposées.

Les secrets et les valeurs de production doivent être conservés dans le fichier `.env`, qui ne doit jamais être versionné.

## Technologies utilisées

| Technologie | Utilisation |
|---|---|
| PHP 8.2+ | Langage du backend |
| Laravel 12 | Framework web, API, validation, sécurité et files d'attente |
| Blade | Rendu serveur du dashboard |
| Eloquent ORM | Modèles, relations et accès aux données |
| MySQL | Base de données principale |
| Tailwind CSS 4 | Styles de l'interface |
| Vite 7 | Compilation et serveur de développement des assets |
| JavaScript et Axios | Interactions côté client et requêtes HTTP |
| Composer | Gestion des dépendances PHP |
| npm | Gestion des dépendances front-end |
| PHPUnit 11 | Tests unitaires et fonctionnels |

