# Audit des faux positifs — état avant correction

## 1. Architecture actuelle

Le checkpoint est un `OrderedDict` contenant uniquement dix tenseurs de `state_dict`, sans classes ni métadonnées. Les formes prouvent trois convolutions `3→32→64→128`, un pooling adaptatif `8×8`, une couche bottleneck `8192→64` et un classifieur `64→2`. Le notebook d'entraînement et `model.py` ont les mêmes paramètres et activations. Le notebook retourne `(logits, z)`, tandis que `model.py` retourne seulement `logits`; cette différence ne change ni les paramètres ni les logits. Le chargement existant est strict par défaut, mais ne l'exprime pas explicitement.

## 2. Chaîne complète de prédiction

`ImageFolder('data_set')` a été exécuté le 13 juillet 2026 et retourne :

```text
classes = ['Fire', 'No_Fire']
class_to_idx = {'Fire': 0, 'No_Fire': 1}
```

Le notebook entraîne avec `CrossEntropyLoss` et deux logits. Validation et inférence utilisent RGB, `Resize((224,224))`, `ToTensor()` puis normalisation ImageNet (`mean=[0.485,0.456,0.406]`, `std=[0.229,0.224,0.225]`). Le service applique un unique softmax, choisit son maximum et définit `is_fire` par `index == 0`. Il appelle `eval()` et `torch.inference_mode()`. L'upload et FastAPI passent une PIL RGB au service. La webcam convertit correctement la frame OpenCV BGR avec `cv2.COLOR_BGR2RGB` avant PIL.

## 3. Hypothèses possibles

- **Labels inversés : réfuté.** Dataset, sortie du notebook et service concordent sur `Fire=0`.
- **Architecture/checkpoint incompatibles : réfuté.** Toutes les formes concordent avec `bottleneck_dim=64` et deux sorties.
- **Sigmoid/softmax/logits mal utilisés : réfuté.** Deux logits + un seul softmax + argmax sont corrects.
- **BGR passé comme RGB : réfuté dans le service actuel**, mais risque important à prévenir par test. Une simulation volontaire BGR-directe transforme notamment `Safe_Fire (3).png` de `no_fire=0,9914` en `fire=0,9996`.
- **Différence entraînement/inférence : réfutée pour la validation.** Taille, ordre et normalisation concordent. L'augmentation aléatoire est correctement réservée au train.
- **Mauvaise généralisation, biais et surconfiance : confirmés.** Le modèle simple, entraîné sur seulement 1 112 images, n'a aucune classe « hors domaine » et softmax peut saturer arbitrairement sur une scène inconnue.

## 4. Preuves trouvées

La vraie capture normale `storage/detections/fire_20260712_224650_007999.jpg` (personne devant un mur, sans feu/fumée) donne, avec le pipeline officiel :

```text
logits = [6.5362, -1.1107]
probabilities = [0.999523, 0.000477]
prediction = fire
```

L'écart de logits de 7,6469 explique mathématiquement les 99,95 %. PIL et le chemin webcam BGR→RGB donnent exactement les mêmes résultats sur les images testées. Le modèle n'est donc pas victime d'une conversion couleur au moment de cette reproduction : il extrapole avec une très forte marge sur une scène absente de son domaine d'entraînement.

Les anciens JPEG contiennent l'annotation rouge ajoutée après l'inférence. Pour exclure cette contamination du diagnostic, les 70 pixels supérieurs ont été retirés : la dernière capture reste `fire=0,9998477`. Le texte n'explique donc pas le faux positif; la correction sauvegarde désormais la frame brute avant annotation.

Le dataset est équilibré en quantité (`556/556`) mais pas représentatif : `Fire` contient des fichiers `Fire` et `Smoke`; `No_Fire` contient `Artificial_Fire` et `Safe_Fire`. Une personne dans une pièce ordinaire n'est pas une catégorie explicitement couverte. Le split du notebook est aléatoire au niveau fichier et peut placer des images visuellement proches dans train et test. Le CNN ne contient ni transfert d'apprentissage, ni dropout, ni batch normalization, ni calibration. La capture sauvegardée contient aussi le texte rouge dessiné après la prédiction; ce texte n'a pas causé la prédiction courante, mais pollue les diagnostics ultérieurs de la capture.

## 5. Corrections nécessaires

1. Enregistrer l'ordre réel et le preprocessing dans des métadonnées versionnées, puis les valider contre le checkpoint.
2. Centraliser le preprocessing PIL/OpenCV et tester explicitement BGR→RGB.
3. Charger avec `strict=True`, contrôler deux sorties et `bottleneck_dim=64`.
4. Retourner les probabilités des deux classes et ajouter des logs debug configurables pour rendre la saturation observable.
5. Sauvegarder la frame brute avant annotation afin de ne pas contaminer les preuves.
6. Auditer dataset et prédictions sur un échantillon/test reproductible, y compris les captures webcam normales.
7. Maintenir seuil, frames consécutives et cooldown comme protections opérationnelles, sans les présenter comme correction du modèle.

## 6. Risques résiduels

Même avec une chaîne correcte, le checkpoint reste biaisé et surconfiant hors domaine. Le softmax binaire force toute image vers l'une des deux classes. Une correction définitive nécessite des négatifs difficiles (personnes, murs, pièces, éclairages chauds), un split par groupes/scènes, une architecture préentraînée et une calibration mesurée. Le système ne doit pas être considéré comme un détecteur incendie certifié.
