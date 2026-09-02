# Rapport Phase 4 — Intelligence artificielle

Date : 29 juillet 2026

## 1. Résumé exécutif

**Statut : Infrastructure IA prête — entraînement réel en attente.**

Le dataset YOLO et son pipeline existaient déjà dans `../Python`; ils n'ont pas
été dupliqués. L'API Laravel de détection et un Edge Agent Laravel ont été
ajoutés. Aucun entraînement complet, aucune évaluation du modèle spécialisé et
aucun benchmark industriel ne sont revendiqués.

## 2. État initial

| Élément | Présent | Emplacement | État | Action |
|---|---|---|---|---|
| Dataset | oui | `../Python/fire_factory_dataset` | 20 309 assets préparés | revalider sur machine sans limite de temps |
| Labels | oui | `../Python/fire_factory_dataset/labels` | 20 309 fichiers | conserver la traçabilité |
| `data.yaml` | oui | `../Python/fire_factory_dataset/data.yaml` | `fire=0`, `smoke=1` | ne pas modifier l'ordre |
| Modèle initial | oui | `../Python/yolo11n/s/m.pt` | poids Ultralytics génériques | fine-tuning requis |
| Environnement Python | oui | `../Python/.venv` | Python 3.12.10, CPU | ajouter pytest ou créer un venv Edge |
| GPU/CUDA | non | système audité | PyTorch CPU, CUDA absent | entraîner sur machine GPU adaptée |
| Edge Agent | partiel | `edge-agent` | composants et tests rapides prêts | test caméra/API réel requis |
| API détection | oui | `POST /api/edge/cameras/{camera}/detections` | intégrée et testée | recette Edge réelle |

La branche `phase-4-ai` n'a pas pu être créée : `.git` est en lecture seule dans
le sandbox. Le dépôt était propre sur `develop`; aucun commit de sauvegarde vide
n'a été fabriqué.

## 3. Dataset

Classes : `fire` (0) et `smoke` (1). Version logique : `fire-smoke-dataset-v1`.

| Split | Images | Labels | Positives | Négatives |
|---|---:|---:|---:|---:|
| train | 14 411 | 14 411 | 11 552 | 2 859 |
| validation | 2 812 | 2 812 | 2 812 | 0 |
| test | 3 086 | 3 086 | 2 644 | 442 |
| total | 20 309 | 20 309 | 17 008 | 3 301 |

L'artefact existant `../Python/reports/DATASET_VALIDATION_FIXED.json` déclare
zéro annotation invalide restante, zéro fichier non apparié et zéro fuite
critique de groupe. Le contrôle courant complet a dépassé 300 secondes sans
produire de sortie; il devra être rejoué avant entraînement. L'absence de
hard negatives explicitement documentés reste un avertissement.

## 4. Environnement Python

- Python : 3.12.10.
- OS : Windows 11.
- PyTorch : 2.13.0+cpu.
- Ultralytics : 8.4.95.
- OpenCV : 5.0.0.
- CUDA/GPU : indisponible; `nvidia-smi` absent.
- pytest : absent des environnements interrogés.
- CPU/RAM : inventaire WMI refusé par le sandbox.
- Device retenu pour les contrôles : CPU.

## 5. Modèle et configuration

Les poids `yolo11n.pt`, `yolo11s.pt` et `yolo11m.pt` ne sont pas des modèles
feu/fumée adaptés. Le choix de départ recommandé pour un smoke training est
`yolo11s.pt`, sous réserve du profil matériel Edge. La configuration existante
prévoit 640 px, batch 16, 100 epochs, patience 20, seed 20260713 et quatre
workers. Elle référence aussi `yolo11l.pt`, absent de l'inventaire : cette
expérience doit être retirée ou son poids acquis explicitement avant exécution.
Aucun modèle final n'est sélectionné.

## 6. Entraînement et artefacts

- Smoke training : anciens dossiers `sanity_check` présents avec `args.yaml`,
  mais sans `best.pt`, `last.pt`, métriques ni preuve de réussite.
- Training complet : non exécuté.
- Epochs réelles prouvées : 0.
- `best.pt` : absent.
- `last.pt` : absent.
- Courbes et configuration finale : absentes.

Commande existante à exécuter depuis `../Python` après revalidation :

```powershell
.\.venv\Scripts\python.exe scripts\fire_dataset_pipeline.py validate
.\.venv\Scripts\python.exe scripts\fire_dataset_pipeline.py train --experiments configs\yolo_experiments.json
```

## 7. Évaluation et métriques

Décision : **Evaluation incomplete**.

Precision, Recall, mAP50, mAP50-95, F1, confusion matrix, faux positifs/heure,
faux négatifs, délai de confirmation, latence et FPS du modèle spécialisé sont
indisponibles. Aucune valeur n'est remplacée par zéro ou inventée.

## 8. Tests images/vidéos et benchmark

Non exécutés faute de poids spécialisés `best.pt` et de vidéo usine annotée.
Les scripts historiques savent évaluer, tester des médias et exporter, mais leur
exécution avec des poids génériques ne constituerait pas une validation métier.

## 9. Filtrage temporel

`TemporalDetectionFilter` maintient des seuils distincts fire/smoke, une fenêtre
de confirmation, un nombre minimal de frames, un cooldown et un timeout de fin.
Les états sont `rejected`, `candidate`, `confirmed`, `cooldown` et `ended`.
Six tests rapides Edge couvrent le filtre et la file offline.

## 10. Edge Agent

L'agent charge sa configuration depuis l'environnement, refuse de démarrer sans
poids, ouvre/reconnecte OpenCV, limite le FPS, vérifie strictement les classes du
modèle, applique le filtre temporel, génère un UUID et un snapshot aléatoire,
envoie heartbeat/détection et met les événements en file SQLite lors des erreurs.
`dry_run` n'envoie rien.

## 11. API Laravel

L'endpoint réutilise `auth.edge`, le scope `event:create` et une limite
60 requêtes/minute/client. Il refuse tenant différent, caméra désactivée,
timestamp rejoué, champs inconnus, classe/confiance/bbox/dimensions invalides et
snapshot non image. La factory est toujours celle de l'API Client.

## 12. Idempotence, workflow et snapshots

`event_uuid` est unique. Un retry identique renvoie `already_received`; une
collision cross-tenant/caméra est rejetée. La transaction crée un Fire Event
`detected`, sa détection, une alerte `pending` et un audit. Une validation humaine
reste nécessaire. Les snapshots sont privés, randomisés et supprimés si la
transaction échoue.

## 13. Offline et logs

La file SQLite conserve payload, snapshot, ordre, tentatives et prochaine
tentative. Retry exponentiel avec jitter, plafond de délai, capacité maximale et
suppression après accusé sont présents. Les logs rotatifs excluent token, secret
et image brute.

## 14. Sécurité et sûreté industrielle

L'IA est une couche d'assistance et ne remplace pas un système incendie certifié.
Elle ne commande ni sprinkler, ni arrêt électrique, ni machine. Les risques
incluent vapeur/fumée, poussière, reflets, lumière orange, caméra obstruée, perte
réseau, petits feux cachés et dérive du domaine.

## 15. Tests

- Edge rapides : 6/6 réussis via `unittest`.
- pytest : non exécutable, dépendance absente.
- Laravel ciblés : 6 tests, 34 assertions.
- Laravel complet : 58 tests, 379 assertions.
- Routes Laravel : 116, dont la route Edge avec les quatre middleware attendus.
- Migrations : toutes appliquées, aucune migration Phase 4 créée.
- Vite 7.3.6 : build réussi, 55 modules transformés.
- npm install : réussi; avertissement `allow-scripts` pour `esbuild`.
- Composer install : tentative Phase 4 interrompue par le timeout sandbox après
  180 secondes; les dépendances installées ont néanmoins permis tous les tests.
- Tests modèle/caméra/RTSP/API réelle : non exécutés.

## 16. Limites et actions manuelles

Rejouer la validation dataset complète, valider licences/hard negatives, créer
un environnement GPU reproductible, exécuter smoke puis training complet,
évaluer sur test indépendant et vidéos usine, benchmarker le matériel cible,
installer pytest, tester RTSP/reconnexion/queue/heartbeat avec l'API réelle,
surveiller la rétention disque et approuver le modèle au minimum pour pilote.

## 17. Décision de déploiement

**Rejected pour production; Evaluation incomplete.** L'infrastructure peut être
revue en développement, mais aucun modèle spécialisé n'est déployable.

## 18. Prochaines étapes

1. Valider à nouveau le dataset et sa licence.
2. Exécuter un smoke training GPU puis contrôler les losses/artefacts.
3. Entraîner et évaluer sur le Test Set indépendant.
4. Mesurer les vidéos usine et fausses alertes/heure/caméra.
5. Piloter Edge + Laravel avec confirmation humaine et rollback.
