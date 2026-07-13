# Audit initial du dataset incendie usine

Date de l'audit : 2026-07-13  
Racine : `fire_factory_dataset/`  
Statut : **INVALIDE — entraînement interdit**

## Méthode et périmètre

L'inventaire a couvert récursivement les sources brutes, les copies `images/` et
`labels/`, `data.yaml` et les trois fichiers metadata. Les 20 568 images préparées
ont été décodées avec OpenCV, les labels YOLO ont été analysés ligne par ligne et
les 100 XML externes ont été parsés. Les doublons exacts utilisent SHA-256. Les
quasi-doublons signalés utilisent un dHash 64 bits (distance de Hamming <= 2) :
ce sont des candidats à regrouper/revoir, pas des suppressions automatiques.

## Inventaire par source

| Source | Images | Vidéos | Annotations | Classes déclarées | Licence |
|---|---:|---:|---|---|---|
| `raw/d_fire` | 20 568 JPG | 0 | 20 568 TXT YOLO | source : `0=smoke`, `1=fire` | non documentée localement |
| `raw/factory_videos` | 0 | 0 | aucune | — | — |
| `raw/factory_images` | 0 | 0 | aucune | — | — |
| `raw/external_datasets/Datacluster` + `Annotations` | 100 JPG | 0 | 100 XML Pascal VOC | `fire` uniquement | non documentée localement |
| copie `images/` + `labels/` | 20 568 JPG | 0 | 20 568 TXT YOLO | encore dans l'ordre source | héritée, non documentée |

La provenance précise et le droit de réutilisation ne sont pas démontrables avec
les fichiers présents. Le nom Datacluster est un indice de provenance, pas une
licence. Aucune source ne doit être publiée ou utilisée en production avant que
`metadata/sources.csv` contienne une licence vérifiée.

## État de la copie préparée

| Mesure | Train | Validation | Test | Total |
|---|---:|---:|---:|---:|
| images | 16 370 | 4 198 | 0 | 20 568 |
| labels | 16 370 | 4 198 | 0 | 20 568 |
| images avec au moins une box valide | 13 745 | 3 447 | 0 | 17 192 |
| labels négatifs vides | 2 575 | 744 | 0 | 3 319 |
| boxes valides classe source 0 (`smoke`) | 10 295 | 2 933 | 0 | 13 228 |
| boxes valides classe source 1 (`fire`) | 17 161 | 4 979 | 0 | 22 140 |
| fichiers contenant une annotation invalide | 224 | 54 | 0 | 278 |
| lignes d'annotation invalides | 268 | 63 | 0 | 331 |

Après remapping explicite, les totaux sémantiques attendus sont donc 22 140 boxes
`fire` (ID final 0) et 13 228 boxes `smoke` (ID final 1), avant exclusion ou
correction des annotations invalides.

### Défauts d'appariement

- Train, images sans label homonyme :
  `7-abstract-cigarette-smoke_jpg.rf.e84652f093030edb261f2b07f4871e4e`,
  `fire_CV000100_jpg.rf.3ff5199b38a224a63fa0b261afb10837`.
- Train, labels sans image homonyme :
  `7-gas-leak_jpg.rf.a5ecde54f41914844bbbfa18d8148fcb`,
  `fire_CV000100_jpg.rf.d780962ddd71aed7f23901d0cff8169e`.
- Validation, image sans label homonyme :
  `fire_CV000100_jpg.rf.d780962ddd71aed7f23901d0cff8169e`.
- Validation, label sans image homonyme :
  `fire_CV000100_jpg.rf.3ff5199b38a224a63fa0b261afb10837`.

Les fichiers `fire_CV000100` semblent avoir été croisés entre train et validation.
Ils ne doivent pas être réappariés automatiquement : les variantes Roboflow peuvent
avoir subi des transformations différentes. Les entrées `7-abstract...` et
`7-gas-leak...` ne décrivent pas le même asset.

### Validité géométrique

- 317 boxes dépassent au moins légèrement les limites de l'image (255 train,
  62 validation).
- 14 boxes ont une largeur ou hauteur nulle (13 train, 1 validation).
- Aucun ID autre que 0 ou 1 n'a été trouvé dans les lignes analysées.
- Les 57 fichiers non vides ne contenant aucune box valide ne sont ni des positifs
  valides ni des négatifs valides.

Aucune box n'a été inventée ou corrigée pendant cet audit. Les dépassements peuvent
être proposés au clipping, mais chaque action doit être inscrite dans
`annotation_review.csv`; les boxes nulles doivent être exclues ou réannotées par un
humain.

## Source VOC externe

- 100 XML valides et 100 images appariées par nom.
- 122 boxes `fire`, toutes géométriquement valides.
- 99 images positives et 1 image sans objet (négatif potentiel à confirmer).
- 35 dimensions différentes; formats dominants : 352x640 (15), 1080x1920 (13),
  3120x4160 (10).
- Aucune classe `smoke` dans cette source.

## Intégrité, dimensions et doublons

- Images préparées décodables : 20 568/20 568; corrompues : 0.
- Dimensions distinctes : 2 693.
- Étendue : largeur 162–4 288 px; hauteur 150–2 848 px.
- Dimensions dominantes : 640x640 (7 698), 1280x720 (4 079),
  640x360 (2 457), 480x360 (573), 640x352 (553).
- Groupes de doublons binaires exacts dans la copie préparée : 0.
- Paires dHash <= 2 : 51 136, dont 15 262 entre train et validation.

Le dHash peut rapprocher à tort des images uniformes ou très sombres. Ces nombres
sont donc un signal de revue. Ils ne justifient aucune suppression automatique.

## Fuites de données

Le split actuel est un split par fichier et non par scène/vidéo. Au moins dix
familles de noms traversent train et validation :

| Famille | Images concernées | Constat |
|---|---:|---|
| `WEB` | 8 495 | train et validation |
| `fire_CV` | 5 038 | train et validation |
| `AoF` | 3 146 | train et validation; frames voisines quasi identiques |
| `smoke_CV` | 2 421 | train et validation |
| `PublicDataset` | 1 055 | train et validation |
| `video_chay_mp4` | 181 | même vidéo dans les deux splits |
| `bothFireAndSmoke_CV` | 144 | train et validation |
| `indoor_night_heptance_001` | 48 | même séquence dans les deux splits |
| `fire2_mp4` | 5 | même vidéo dans les deux splits |
| vidéo forklift | 3 | même vidéo dans les deux splits |

Exemples mesurés : `AoF00040` (train) / `AoF00041` (validation), dHash 2;
`AoF00015` (train) / `AoF00029` (validation), dHash 1. Le préfixe seul ne permet
pas de déterminer de façon fiable les limites de toutes les vidéos concaténées.
`scene_id` et `video_id` doivent donc être renseignés ou confirmés avant un nouveau
split; les déduire arbitrairement risquerait de recréer une fuite.

## Configuration et metadata

- `data.yaml` pointe vers `/kaggle/working/D Fire Dataset` et des sous-chemins
  inexistants dans ce dépôt.
- Il déclare `names: ['smoke', 'fire']`; l'ordre final requis est
  `0: fire`, `1: smoke`. Modifier seulement le YAML sans remapper les TXT rendrait
  les labels sémantiquement faux.
- Les trois CSV metadata existaient avec une taille nulle. Le pipeline initialise
  leurs en-têtes, mais il ne fabrique aucune provenance absente.

## Hard negatives et représentativité usine

Les dossiers usine locaux sont vides. Aucun inventaire démontrable de welding,
sparks, steam, dust, red lights ou reflections n'est disponible. Les 3 319 labels
vides ne prouvent pas à eux seuls la présence ni la qualité de ces hard negatives.
Le dataset ne permet donc pas encore de mesurer le taux de fausses alertes dans
une usine réelle.

## Conclusion et portes de validation

L'entraînement est bloqué jusqu'à satisfaction de toutes les conditions suivantes :

1. licences et provenance renseignées;
2. mapping `smoke 0 -> smoke 1` et `fire 1 -> fire 0` appliqué sur une copie;
3. paires orphelines exclues ou revues humainement;
4. 331 lignes invalides corrigées avec traçabilité ou exclues;
5. `scene_id`/`video_id` vérifiés et split par groupe recréé avec seed fixe;
6. jeu test non vide, indépendant et représentatif de l'usine;
7. quasi-doublons inter-splits revus;
8. hard negatives usine ajoutés et documentés.

En conséquence, aucune métrique YOLO réelle, aucun « meilleur modèle » et aucune
précision usine ne sont annoncés à ce stade.
