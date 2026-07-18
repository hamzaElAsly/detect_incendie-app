# API FireFactory SaaS

Base URL: `/api`. Les routes utilisateur utilisent `Authorization: Bearer <token>` et les routes tenant exigent `X-Factory-ID`, identique au paramètre `{factory}`.

## Dashboard

- `GET /factories/{factory}/dashboard/summary`
- `GET /factories/{factory}/dashboard/events-chart?days=30`
- `GET /factories/{factory}/dashboard/camera-status`
- `GET /factories/{factory}/dashboard/recent-events?limit=10`
- `GET /factories/{factory}/dashboard/metrics`

## Paramètres et audit

- `GET|PATCH /factories/{factory}/settings`
- `GET /factories/{factory}/audit-logs?action=...`
- `POST /factories/{factory}/events/{event}/media`, multipart `image` (JPEG/PNG/WebP, 10 MiB) et/ou `video` (MP4/WebM/MOV, 50 MiB).

## Santé

- `GET /health` vérifie base, queue et stockage.

Format succès : `success`, `message`, `data`, `meta`. Format erreur attendu : `success=false`, `message`, `errors`.

> Intégration en attente : ces déclarations résident dans `routes/platform.php`; le sandbox doit autoriser l'ajout de `require __DIR__.'/platform.php';` dans `routes/api.php`.

# API REST

Base URL: `/api`. Toutes les réponses sont en JSON. Les routes privées attendent `Authorization: Bearer <token>`.

## Authentification

| Méthode | Route | Auth | Description |
|---|---|---|---|
| POST | `/auth/register` | Non | Crée utilisateur, première usine et rôle `factory_owner` |
| POST | `/auth/login` | Non | Authentifie et émet un jeton |
| POST | `/auth/logout` | Oui | Révoque le jeton courant |
| GET | `/auth/me` | Oui | Profil et usines accessibles |
| POST | `/auth/forgot-password` | Non | Demande de réinitialisation non énumérable |
| POST | `/auth/reset-password` | Non | Réinitialise le mot de passe et révoque les jetons |
| POST | `/auth/refresh` | Oui | Rotation atomique du jeton courant |

Inscription : `name`, `email`, `password`, `password_confirmation`, `factory_name`. Connexion : `email`, `password`, `device_name` optionnel.

## Contexte tenant

Les endpoints métier utilisent l’en-tête obligatoire `X-Factory-ID: <ULID>`. Le middleware charge l’usine active puis vérifie l’appartenance avant le contrôleur. Un super administrateur pourra explicitement changer de contexte. Ne jamais accepter un `factory_id` métier depuis le corps de requête : il provient de `FactoryContext`.

| Méthode | Route | En-têtes | Description |
|---|---|---|---|
| GET | `/factory-context` | Bearer + X-Factory-ID | Vérifie et retourne le tenant courant |

Les erreurs suivent `{ "message": "...", "errors": {...} }`. Limites actuelles : 10 requêtes/minute pour l’authentification, 120/minute pour l’API authentifiée.
