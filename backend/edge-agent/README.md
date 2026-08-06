# Detect Incendie Edge Agent

Agent Python 3.12 offline-first : capture RTSP/webcam/fichier, inférence YOLO fire/smoke, confirmation temporelle, snapshot compressé, envoi Bearer tenant-scoped vers Laravel et reprise SQLite. Il ne commande aucun équipement physique.

## Limitation actuelle

Aucun poids spécialisé `best.pt` n’est présent dans le projet. La validation métier du modèle est donc **fausse** (`EDGE_MODEL_BUSINESS_VALIDATED=false`). `EDGE_MOCK_DETECTOR=true` permet les tests d’exploitation sans modèle. Les poids génériques sont refusés par défaut; `EDGE_ALLOW_GENERIC_MODEL=true` les autorise uniquement avec `EDGE_DRY_RUN=true` pour un test technique, sans produire de validation métier. Aucune caméra réelle ni pipeline caméra → Edge → API n’est déclaré validé par cette livraison.

## Installation et démarrage sûr

```powershell
cd edge-agent
python -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
Copy-Item .env.example .env
# Renseigner EDGE_AGENT_ID et EDGE_SOURCE; conserver dry-run/mock pour le premier essai.
python scripts/run_agent.py --config-check
python scripts/run_agent.py
```

Pour un envoi réel, renseigner `EDGE_API_BASE_URL`, `EDGE_API_CLIENT_TOKEN`, `EDGE_CAMERA_ID`, utiliser un client avec `camera:heartbeat` et `event:create`, puis définir `EDGE_DRY_RUN=false`. Ne jamais ajouter `.env` au dépôt. Aucun `factory_id` n’est envoyé : Laravel déduit le tenant du client API et vérifie la caméra.

## Diagnostics

```powershell
python scripts/test_rtsp.py --source "rtsp://user:password@host/stream" --frames 20
python scripts/test_api_connection.py
python scripts/simulate_offline.py
python -m unittest discover -s tests -v
```

Le test RTSP masque les identifiants et retourne un code non nul si l’ouverture ou la lecture échoue. Les logs JSON rotatifs sont écrits dans `storage/logs`; tokens, secrets et credentials RTSP y sont filtrés.

`scripts/simulate_offline.py` utilise un dossier temporaire : il simule un crash pendant l’upload, vérifie le retour `sending` → `retry`, puis l’accusé et la suppression du snapshot sans toucher à la queue de production. La recette réelle est décrite dans `PHASE_5_MANUAL_CHECKLIST.md`.

## Comportement réseau et fichiers

- RTSP utilise TCP avec OpenCV/FFmpeg lorsque disponible, détecte ouverture impossible, frame vide, timeout, résolution modifiée et flux figé, puis reconnecte avec backoff exponentiel et jitter.
- Les réponses 429/500/502/503/504 et erreurs réseau sont rejouées avec `Retry-After`; 400/401/403/404/409/422 sont définitives. HTTP 200 `already_received` est un succès idempotent.
- L’UUIDv4 est créé une seule fois à la confirmation et reste stable dans la file et tous les retries.
- Les snapshots JPG/PNG/WebP sont randomisés, limités à 10 Mio, supprimés après accusé et conservés avec un événement offline.
- SQLite utilise WAL, FIFO et les statuts `pending`, `sending`, `retry`, `sent`, `failed`; un ancien `sending` redevient `retry` au redémarrage.
- Les anciennes queues Phase 4 utilisant la table `events` sont importées sans suppression vers `queued_events` au premier démarrage.

Voir `service/edge-agent.service` et `service/windows-service.md` pour l’exploitation.
