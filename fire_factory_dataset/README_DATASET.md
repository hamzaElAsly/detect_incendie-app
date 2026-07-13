# Dataset de détection fire/smoke

Classes finales obligatoires : `0: fire`, `1: smoke`.

`raw/` est immuable. Toute conversion est d'abord écrite dans `staging/` puis
revue avant promotion vers `images/` et `labels/`. Un négatif possède un TXT vide.
Welding, sparks, steam, dust, red lights et reflections sont des hard negatives,
jamais des boxes `fire` ou `smoke` sans annotation humaine correspondante.

La seed de référence est `20260713`. Le split est réalisé par `group_id`, dérivé
d'un `video_id` ou `scene_id` validé; jamais par frame. Les trois CSV de
`metadata/` constituent la traçabilité requise.

Le dataset actuel est invalide. Lire `reports/DATASET_INITIAL_AUDIT.md` et lancer :

```powershell
python scripts/fire_dataset_pipeline.py validate
```

La commande d'entraînement intègre cette validation et refuse tout dataset
invalide. Le CNN historique et les services de l'application ne sont pas modifiés
par ce pipeline.
