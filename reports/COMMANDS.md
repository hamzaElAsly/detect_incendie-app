# Commandes reproductibles

Toutes les commandes sont lancées depuis la racine du projet sous PowerShell.
La seed fixe est `20260713`. Les conversions écrivent uniquement dans `staging/`
et ne modifient jamais `raw/`.

```powershell
python scripts/fire_dataset_pipeline.py init-metadata
python scripts/fire_dataset_pipeline.py validate
python scripts/fire_dataset_pipeline.py convert-d-fire
python scripts/fire_dataset_pipeline.py convert-voc
python scripts/fire_dataset_pipeline.py merge-pools --inputs fire_factory_dataset/staging/d_fire fire_factory_dataset/staging/datacluster --output fire_factory_dataset/staging/merged
```

Après revue humaine et remplissage de `sources.csv` (licence et `scene_id` ou
`video_id` obligatoires), créer une nouvelle racine candidate, sans écraser le
dataset courant :

```powershell
python scripts/fire_dataset_pipeline.py build-splits --pool fire_factory_dataset/staging/merged --output fire_factory_dataset_candidate --ratios 0.70 0.15 0.15
python scripts/fire_dataset_pipeline.py --dataset fire_factory_dataset_candidate validate
```

Après validation et promotion contrôlée du candidat :

```powershell
python scripts/fire_dataset_pipeline.py validate
python scripts/fire_dataset_pipeline.py train --experiments configs/yolo_experiments.json
python scripts/fire_dataset_pipeline.py evaluate --weights runs/fire_factory/small/weights/best.pt --output reports/evaluation_small.json
python scripts/fire_dataset_pipeline.py test-media --weights runs/fire_factory/small/weights/best.pt --sources path/to/factory_test.mp4 path/to/factory_test.jpg --output reports/factory_small
python scripts/fire_dataset_pipeline.py select-model --evaluations reports/evaluation_small.json reports/evaluation_medium.json reports/evaluation_large.json
python scripts/fire_dataset_pipeline.py export --weights runs/fire_factory/small/weights/best.pt --formats onnx torchscript --output exports/yolo_fire_smoke
```

`train` relance systématiquement `validate`, sauvegarde la validation dans
`reports/DATASET_VALIDATION.json`, journalise chaque configuration dans
`reports/TRAINING_RUNS.jsonl`, exige des poids `.pt` préentraînés et refuse de
démarrer si une erreur subsiste.

## État de l'environnement audité

- Python dispose de PyTorch CPU (`torch 2.13.0+cpu`).
- CUDA n'est pas disponible.
- `ultralytics` n'est pas installé.
- Aucun entraînement n'a été exécuté, à la fois parce que le dataset est invalide
  et parce que l'environnement d'entraînement n'est pas installé.
