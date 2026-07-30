# Model Card — fire-smoke-yolo-v1.0.0

Statut : `training_pending`.

- Objectif : détecter des bounding boxes `fire` et `smoke`.
- Dataset prévu : `fire-smoke-dataset-v1`, 20 309 images.
- Framework prévu : Ultralytics YOLO/PyTorch.
- Poids : aucun modèle spécialisé produit.
- Métriques : indisponibles.
- Matériel : cible Edge à déterminer; environnement actuel CPU-only.
- Seuils initiaux de pilote : fire 0,90; smoke 0,85; à recalibrer.
- Usage permis : assistance à la surveillance avec confirmation humaine.
- Usages interdits : commande automatique d'équipements critiques, certification
  incendie, surveillance sans heartbeat ni procédure de repli.
- Limites : vapeur, poussière, soudure, soleil/reflets, nuit, occlusion, petits
  feux, dérive de caméra et perte réseau.
- Rollback : désactiver la version Edge, restaurer le modèle pilote précédent et
  conserver les événements/audits.
