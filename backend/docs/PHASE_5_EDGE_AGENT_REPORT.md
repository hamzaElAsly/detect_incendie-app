# Rapport Phase 5 — Edge Agent

Date : 4 août 2026

## Statut

**Implémentation technique complétée et testée localement; validation réelle caméra/modèle/API en attente.**

L’agent existant a été complété en place. Aucun changement Laravel, aucune migration, aucun secret et aucun `factory_id` faisant autorité n’ont été ajoutés.

## Preuves techniques

- Configuration stricte complète dans `edge-agent/.env.example` et `src/settings.py`.
- RTSP/webcam/fichier, états de santé, frames vides/figées, timeouts, reconnexion backoff+jitter et fermeture propre dans `camera_stream.py`.
- Chargement unique, classes strictes, fallback CUDA, warm-up, mock, poids génériques dry-run uniquement et validation métier explicite dans `model_loader.py`.
- Client Bearer JSON/multipart, timeouts, `Retry-After`, statuts retryables/définitifs et idempotence dans `api_client.py`.
- Queue SQLite WAL/FIFO avec crash recovery, migration de la queue Phase 4, statuts, capacité, reprise et conservation snapshot dans `offline_queue.py`.
- Snapshots randomisés, redimensionnés, compressés, formats autorisés, rétention protégée et limite 10 Mio dans `snapshot_service.py`.
- Heartbeat non bloquant au démarrage, périodique, changement d’état et arrêt best-effort dans `heartbeat_service.py`.
- 50 tests Python locaux réussis, dont un serveur HTTP local réel pour Bearer/multipart/503/retry et un pipeline dry-run frame → filtre → événement → snapshot.
- Runtime isolé Phase 4 vérifié : Python 3.12.10, OpenCV 5.0.0, Requests 2.34.2, Ultralytics 8.4.95; `pip check` sans dépendance cassée.
- 58 tests Laravel réussis, 379 assertions; contrat Edge, authentification, scopes et isolation tenant inchangés.
- Diagnostic offline autonome réussi : récupération `sending` → `retry`, accusé, suppression de la queue et du snapshot.
- Exécution réelle de l’agent sur une vidéo AVI temporaire réussie : 8 frames, fin de fichier et arrêt propre; cette preuve ne vaut pas validation RTSP réelle.

## Validations non revendiquées

- [ ] Flux RTSP d’une caméra réelle.
- [ ] Pipeline réel caméra → Edge → API Laravel → base de données.
- [ ] Déconnexion réseau physique puis reprise sur l’environnement cible.
- [ ] Modèle spécialisé `best.pt` entraîné et évalué.
- [ ] Validation métier fire/smoke, métriques industrielles, latence et FPS réels.

Tant que ces cases restent ouvertes, utiliser `EDGE_DRY_RUN=true` ou `EDGE_MOCK_DETECTOR=true` selon le test. Les poids génériques ne prouvent jamais la validité métier.
