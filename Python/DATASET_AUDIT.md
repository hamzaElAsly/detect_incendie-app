# Audit du dataset

## Volumétrie

- Total : 1112 fichiers; valides : 1112; invalides : 0.
- `Fire` : 556 (50.0%).
- `No_Fire` : 556 (50.0%).
- Sous-type `Fire/Fire` : 283.
- Sous-type `Fire/Smoke` : 273.
- Sous-type `No_Fire/Artificial_Fire` : 286.
- Sous-type `No_Fire/Safe_Fire` : 270.

## Dimensions et couleurs

- Largeur moyenne/médiane : 400.0/400.0px.
- Hauteur moyenne/médiane : 400.0/400.0px.
- Images avec au moins une dimension inférieure à 224px : 0.
- Couleur RGB moyenne `Fire` (miniatures 16×16) : [126.8, 100.6, 86.7].
- Couleur RGB moyenne `No_Fire` (miniatures 16×16) : [111.6, 83.9, 63.1].

## Intégrité et doublons

- Fichiers invalides : 0.
- Groupes de doublons binaires exacts : 0.
- Paires perceptuellement très proches (dHash distance ≤2) : 333.

Exemples proches:
- `data_set/Fire/Fire (145).png` ↔ `data_set/Fire/Fire (146).png` (distance 2)
- `data_set/Fire/Fire (145).png` ↔ `data_set/Fire/Fire (147).png` (distance 2)
- `data_set/Fire/Fire (146).png` ↔ `data_set/Fire/Fire (147).png` (distance 0)
- `data_set/Fire/Fire (198).png` ↔ `data_set/Fire/Fire (199).png` (distance 1)
- `data_set/Fire/Fire (20).png` ↔ `data_set/Fire/Fire (29).png` (distance 2)
- `data_set/Fire/Fire (233).png` ↔ `data_set/Fire/Fire (235).png` (distance 1)
- `data_set/Fire/Fire (242).png` ↔ `data_set/Fire/Fire (248).png` (distance 2)
- `data_set/Fire/Fire (242).png` ↔ `data_set/Fire/Fire (249).png` (distance 0)
- `data_set/Fire/Fire (242).png` ↔ `data_set/Fire/Fire (250).png` (distance 0)
- `data_set/Fire/Fire (242).png` ↔ `data_set/Fire/Fire (251).png` (distance 0)
- `data_set/Fire/Fire (243).png` ↔ `data_set/Fire/Fire (244).png` (distance 2)
- `data_set/Fire/Fire (243).png` ↔ `data_set/Fire/Fire (245).png` (distance 1)
- `data_set/Fire/Fire (243).png` ↔ `data_set/Fire/Fire (246).png` (distance 1)
- `data_set/Fire/Fire (243).png` ↔ `data_set/Fire/Fire (248).png` (distance 1)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (130).png` (distance 1)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (131).png` (distance 0)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (132).png` (distance 1)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (133).png` (distance 0)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (134).png` (distance 0)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (135).png` (distance 1)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (136).png` (distance 1)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (137).png` (distance 0)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (138).png` (distance 0)
- `data_set/Fire/Fire (243).png` ↔ `data_set/No_Fire/Safe_Fire (139).png` (distance 0)
- `data_set/Fire/Fire (244).png` ↔ `data_set/Fire/Fire (245).png` (distance 1)
- `data_set/Fire/Fire (244).png` ↔ `data_set/Fire/Fire (246).png` (distance 1)
- `data_set/Fire/Fire (244).png` ↔ `data_set/No_Fire/Safe_Fire (130).png` (distance 1)
- `data_set/Fire/Fire (244).png` ↔ `data_set/No_Fire/Safe_Fire (131).png` (distance 2)
- `data_set/Fire/Fire (244).png` ↔ `data_set/No_Fire/Safe_Fire (132).png` (distance 1)
- `data_set/Fire/Fire (244).png` ↔ `data_set/No_Fire/Safe_Fire (133).png` (distance 2)

## Risques de qualité

- L'équilibre numérique 50/50 ne garantit pas la représentativité du monde réel.
- `Fire` mélange flammes et fumée; `No_Fire` mélange feux artificiels et scènes dites sûres.
- Le split aléatoire par fichier peut placer des scènes quasi identiques dans plusieurs ensembles.
- Les scènes webcam ordinaires (personnes, murs, bureaux, éclairages intérieurs) ne constituent pas un corpus négatif explicite.
- Les différences de couleur/fond peuvent devenir des raccourcis appris par le CNN.

## Recommandations

Regrouper les images par scène/source avant le split, contrôler manuellement les paires proches, ajouter des négatifs difficiles issus de vraies webcams, séparer les métriques feu/fumée et calibrer le modèle sur un jeu de validation indépendant.
