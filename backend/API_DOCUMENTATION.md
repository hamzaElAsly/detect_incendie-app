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
