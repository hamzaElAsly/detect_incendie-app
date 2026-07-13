# Rapport final — faux positifs webcam

## Cause principale

Le checkpoint généralise mal et produit des logits non calibrés sur des scènes webcam hors de son domaine d'entraînement. Sur la capture normale `fire_20260712_224650_007999.jpg`, il retourne `[6,5362, -1,1107]`. Le softmax de leur écart (`7,6469`) vaut `[0,999523, 0,000477]` : c'est exactement pourquoi l'interface affiche environ 99,96 % de feu. Ce nombre est une préférence forcée entre deux classes connues, pas une probabilité calibrée qu'une flamme soit réellement présente.

La preuve résiste aux contrôles : chemin PIL et chemin webcam BGR→RGB identiques; mapping réel; architecture stricte; un seul softmax; crop supprimant l'ancienne annotation rouge encore classé `fire=0,9998477`. Le threshold à 0,95 ne peut donc pas résoudre ce cas.

## Causes secondaires

- Seulement 1 112 images, sans corpus négatif explicite de personnes/murs/pièces webcam.
- 333 paires perceptuellement quasi identiques, donc risque de fuite avec le split aléatoire par fichier.
- Taxonomie atypique : fumée incluse dans `Fire`, feux artificiels inclus dans `No_Fire`.
- CNN entraîné de zéro, sans dropout, batch normalization, transfert d'apprentissage, calibration ou mécanisme hors domaine.
- Les anciennes captures étaient annotées avant sauvegarde, ce qui polluait les diagnostics ultérieurs (mais pas la prédiction ayant déclenché la capture).
- Une classe `DetectionGate` dupliquée dans les tests masquait l'implémentation réelle.

## Classes et mapping final

Commande exécutée :

```text
classes = ['Fire', 'No_Fire']
class_to_idx = {'Fire': 0, 'No_Fire': 1}
```

Mapping applicatif final : `0→fire`, `1→no_fire`. Sur l'échantillon stratifié, ce mapping donne 96 % d'accuracy contre 4 % pour le mapping inversé. Il est stocké dans `model_metadata.json`.

## Architecture vérifiée

Trois convolutions `3→32→64→128`, ReLU, pooling adaptatif `8×8`, flatten, bottleneck `8192→64`, ReLU et classifieur `64→2`. Le checkpoint est un pur `OrderedDict state_dict`, sans métadonnées; `classifier.weight=(2,64)`. Chargement désormais explicite avec `strict=True`, contrôle des sorties, `eval()` et `torch.inference_mode()`.

## Preprocessing vérifié

RGB, resize 224×224, tenseur puis normalisation ImageNet. Le service webcam vérifie une frame BGR à trois canaux, applique `cv2.COLOR_BGR2RGB`, puis crée la PIL. Cette chaîne est centralisée dans `services/image_preprocessing.py` et partagée par upload, webcam et API.

## Calcul des probabilités

Le modèle fournit deux logits. Le service applique exactement un `softmax(dim=1)`, prend `argmax`, lit la probabilité correspondante et déduit `is_fire` du label `fire`, non d'un index dispersé dans le code. La réponse expose désormais `predicted_index` et les probabilités `fire/no_fire`. `PREDICTION_DEBUG=true` ajoute les détails sûrs aux logs.

## Corrections effectuées

- Métadonnées vérifiées et versionnées; validation checkpoint/métadonnées.
- Preprocessing unique PIL/OpenCV et conversion couleur testée pixel par pixel.
- Chargement strict, résultat de prédiction enrichi et debug configurable.
- Streamlit et FastAPI restent consommateurs du même singleton de service.
- Seuil événement distinct `FIRE_EVENT_THRESHOLD`, sans le présenter comme solution du modèle.
- Capture brute sauvegardée avant dessin de l'annotation.
- Cooldown corrigé : aucune accumulation de frames positives pendant sa durée.
- Suppression du faux `DetectionGate` de test et ajout de tests sur le vrai code/poids.

## Fichiers créés

`FALSE_POSITIVE_AUDIT.md`, `model_metadata.json`, `services/image_preprocessing.py`, `scripts/inspect_checkpoint.py`, `scripts/audit_dataset.py`, `scripts/diagnose_predictions.py`, `DATASET_AUDIT.md`, `prediction_diagnostics.csv`, `PREDICTION_DIAGNOSTICS.md`, `MODEL_RETRAINING_PLAN.md`, `FALSE_POSITIVE_FIX_REPORT.md`.

## Fichiers modifiés

`config.py`, `.env.example`, `services/fire_detection_service.py`, `services/detection_gate.py`, `app.py`, `api.py`, `tests/test_fire_detection.py`, `README.md`.

## Résultats avant/après correction

La correction logicielle ne prétend pas changer les poids : la scène normale reste un faux positif très confiant, ce qui est précisément la preuve qu'un réentraînement est nécessaire. Les interfaces sont maintenant cohérentes, observables et protégées contre les erreurs de mapping/couleur futures. Exemple normal connu `Safe_Fire (3).png` : `no_fire=0,991397`. Exemple feu `test_feu.jpg` : `fire=0,957946`. Captures webcam normales : `fire=0,925640`, `0,998403`, `0,999523`.

Diagnostic stratifié : 100 images dataset, accuracy 96 %, quatre faux positifs en incluant la webcam, trois faux négatifs, confiance moyenne 0,9272. Dataset : 556/556, zéro image invalide, zéro doublon binaire exact, 333 paires dHash très proches.

## Tests exécutés

- `python -m compileall .` : OK.
- `python -m unittest discover -s tests -v` : 17/17 OK.
- `scripts/inspect_checkpoint.py` : OK.
- `scripts/audit_dataset.py` : OK.
- `scripts/diagnose_predictions.py` : OK.
- Streamlit headless : health HTTP 200, arrêt propre.
- FastAPI : `/health` OK avec modèle et MySQL connectés, `/docs` HTTP 200, `/events` HTTP 200.
- API `Safe_Fire (3).png` : `No Fire`, index 1, confiance 0,9914, identique au service.

La webcam physique n'a pas été rouverte automatiquement pendant les tests afin de ne pas capturer l'utilisateur sans action; ses trois captures existantes ont été analysées et la conversion webcam est testée sans matériel.

## Erreurs restantes et réentraînement

Erreur restante : le checkpoint demeure surconfiant hors domaine. Oui, un réentraînement est nécessaire pour corriger réellement ce faux positif; il n'a volontairement pas été lancé. Le plan détaillé se trouve dans `MODEL_RETRAINING_PLAN.md`. Avant cela, collecter et labelliser des scènes webcam négatives difficiles et refaire un split groupé sans quasi-doublons.
