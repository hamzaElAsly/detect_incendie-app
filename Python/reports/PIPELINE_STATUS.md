# État du pipeline YOLO

Date : 2026-07-13

## Résultat réel

Le pipeline d'audit, de conversion, de fusion, de split groupé, de validation,
d'entraînement, d'évaluation média, de sélection et d'export est en place.
L'entraînement n'a pas été lancé : la porte de validation l'a refusé comme prévu.

Erreurs bloquantes mesurées :

- ordre de classes et chemins invalides dans le `data.yaml` d'origine;
- 331 lignes YOLO invalides dans 278 fichiers;
- 3 images sans label homonyme et 3 labels sans image homonyme;
- split test vide;
- metadata de provenance/split non renseignées;
- familles de scènes et vidéos réparties entre train et validation;
- licences non documentées;
- aucun corpus usine ni hard negatives explicitement documentés.

L'audit a décodé 20 568 images sur 20 568, sans corruption et sans doublon
binaire exact. Il a signalé 15 262 paires quasi similaires inter-splits avec
dHash <= 2. Ce dernier nombre est une liste de candidats, pas une preuve que
chaque paire est un doublon.

## Modèles et métriques

Aucun poids YOLO n'a été produit. Les valeurs suivantes sont donc indisponibles,
et non égales à zéro : précision, rappel, F1, mAP50, mAP50-95, métriques par classe,
latence, fausses alertes par heure et rappel sur événements usine.

Le fichier `configs/yolo_experiments.json` prévoit trois tailles préentraînées et
du fine-tuning déterministe. Les noms/poids restent configurables. L'environnement
audité est CPU uniquement et ne contient pas Ultralytics; un environnement GPU
verrouillé devra être installé et consigné avant l'exécution.

## Sélection professionnelle

La sélection exige un test usine annoté complet et construit un front de Pareto :
maximisation de mAP50-95 et du rappel `fire`, minimisation des fausses alertes par
heure. Si plusieurs modèles sont non dominés, aucune pondération arbitraire n'est
appliquée : une contrainte métier usine est requise.

## Intégration

L'export ONNX/TorchScript produit un manifeste avec l'ordre `fire=0`, `smoke=1`.
Il ne remplace pas le modèle de l'application. Le CNN historique, son checkpoint,
`model_metadata.json`, Streamlit, FastAPI et le service d'inférence actuel sont
inchangés.

## Vérifications exécutées

- 100 XML VOC convertis en test temporaire : 100 labels, dont 1 négatif vide;
- garde d'entraînement : refus confirmé sur le dataset courant;
- 4 tests unitaires du pipeline : succès;
- suite complète dans `.venv` : 21 tests, succès;
- suite avec le Python système : non exploitable car `torchvision` y est absent.
