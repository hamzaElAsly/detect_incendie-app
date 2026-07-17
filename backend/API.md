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
