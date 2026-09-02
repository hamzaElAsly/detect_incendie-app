# Checklist manuelle Phase 5 — environnement cible

Cette checklist reste volontairement décochée tant que les preuves réelles ne sont pas disponibles. Ne jamais coller un token ou une URL RTSP avec credentials dans un rapport ou un ticket.

## Préconditions

- [ ] Sauvegarde et environnement Laravel cible identifiés; aucune commande `migrate:fresh`.
- [ ] API Client actif, même tenant que la caméra, scopes `camera:heartbeat` et `event:create`.
- [ ] Caméra active et ULID `EDGE_CAMERA_ID` confirmé.
- [ ] Flux RTSP de test autorisé, credentials stockés uniquement dans `.env` protégé.
- [ ] `best.pt` fire/smoke spécialisé présent, classes exactes `fire=0`, `smoke=1`.
- [ ] Évaluation métier du modèle documentée avant `EDGE_MODEL_BUSINESS_VALIDATED=true`.

## Vérifications sans écriture d’événement

```powershell
cd edge-agent
python scripts/run_agent.py --config-check
python scripts/test_rtsp.py --source $env:EDGE_SOURCE --source-type rtsp --frames 20
python scripts/test_api_connection.py --status degraded
python scripts/simulate_offline.py
```

- [ ] `test_rtsp.py` retourne 0, lit plusieurs frames et affiche résolution/FPS sans credentials.
- [ ] Le heartbeat est visible sur la caméra Laravel avec le bon API Client.
- [ ] `simulate_offline.py` affiche les quatre invariants `OK`.
- [ ] En `EDGE_DRY_RUN=true`, les logs montrent `model_business_validated` attendu et aucun Fire Event n’est créé.

## Pipeline réel contrôlé

- [ ] Définir `EDGE_DRY_RUN=false` uniquement sur une caméra et un tenant de recette autorisés.
- [ ] Présenter une scène de test sûre; ne déclencher aucun équipement physique.
- [ ] Vérifier un HTTP 201 et la création tenant-scoped du Fire Event, de la détection, de l’alerte, de l’audit et du snapshot privé.
- [ ] Rejouer le même `event_uuid` en recette et vérifier HTTP 200 `already_received` sans doublon.
- [ ] Vérifier qu’aucun `factory_id` Edge n’est envoyé ou accepté comme autorité.

## Offline et reconnexion réels

- [ ] Couper uniquement le réseau de recette après autorisation; confirmer conservation SQLite + snapshot.
- [ ] Restaurer le réseau; confirmer FIFO, même UUID, accusé unique et suppression du snapshot local.
- [ ] Interrompre puis restaurer le flux RTSP; confirmer états offline/reconnecting/online et reset du filtre temporel.
- [ ] Redémarrer l’agent avec un événement `sending`; confirmer reprise en `retry`.
- [ ] Vérifier arrêt Ctrl+C propre, heartbeat final best-effort et libération de `VideoCapture`.

## Preuves à conserver sans secrets

- [ ] Horodatage UTC, versions agent/modèle, OS/device et commandes exécutées.
- [ ] Codes HTTP et UUID anonymisés.
- [ ] Extraits de logs structurés après contrôle de redaction.
- [ ] Résultats des tests Python et Laravel.
- [ ] Métriques réelles uniquement; aucune valeur supposée ou générique présentée comme métier.
