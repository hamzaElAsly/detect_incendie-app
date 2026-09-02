# Intégration Edge Agent ↔ Laravel

## Contrat

`POST /api/edge/cameras/{camera}/detections`, bearer token API Client, scope
`event:create`. La factory du payload n'est jamais acceptée comme autorité.

Réponses :

- HTTP 201, `created`;
- HTTP 200, `already_received`;
- HTTP 401/403, authentification/scope/tenant;
- HTTP 409, collision UUID d'une autre caméra ou factory;
- HTTP 422, caméra désactivée ou payload invalide;
- HTTP 429, limite dépassée.

Le payload suit le contrat Phase 4 : UUID, timestamp UTC récent, classe
fire/smoke, confiance normalisée, bbox `{x,y,width,height}`, dimensions, modèle,
version, temps d'inférence et nombre de frames de confirmation. `snapshot` est
un fichier multipart optionnel JPG/PNG/WebP de 10 Mo maximum.

## Exploitation

Créer un API Client avec scopes `camera:heartbeat` et `event:create`, placer son
token uniquement dans `.env`, tester avec une caméra non critique, puis vérifier
Fire Event, Alert, Audit Log et média privé. Ne jamais journaliser le token.

Le heartbeat reste sur
`POST /api/edge/cameras/{camera}/heartbeat`. L'Edge Agent met les détections
confirmées en SQLite lorsque Laravel est indisponible et rejoue dans l'ordre avec
backoff et jitter.
