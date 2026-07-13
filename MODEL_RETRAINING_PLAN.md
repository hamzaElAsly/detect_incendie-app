# Plan de réentraînement justifié

## Justification

La chaîne d'inférence est cohérente, mais le checkpoint classe encore trois scènes webcam normales comme `fire` avec jusqu'à 99,9523 %. Le problème résiduel est donc un défaut de généralisation et de calibration. Aucun réentraînement n'a été lancé pendant cet audit.

## Données

1. Revoir manuellement les labels, notamment `Artificial_Fire`, `Safe_Fire` et la sémantique fumée/feu.
2. Dédupliquer par hash perceptuel et regrouper toutes les variantes d'une même scène avant le split.
3. Ajouter des négatifs difficiles : personnes, murs, bureaux, chambres, éclairages chauds/rouges, soleil, écrans, vêtements orange, bruit et faible lumière webcam.
4. Ajouter des positifs webcam réalistes et diversifiés sans partager les mêmes vidéos entre ensembles.
5. Garder un test externe figé, jamais utilisé pour choisir modèle ou seuil.
6. Équilibrer par scène/source plutôt que seulement par nombre de fichiers.

## Split et entraînement

- Split groupé et stratifié train/validation/test (par exemple 70/15/15), avec graine enregistrée.
- Backbone préentraîné léger (MobileNetV3 ou EfficientNet-B0), fine-tuning progressif, dropout et régularisation.
- Augmentations réalistes : crop modéré, flip, luminosité/contraste/couleur, flou, bruit et compression webcam.
- Early stopping sur une métrique de validation orientée sécurité et sauvegarde du meilleur checkpoint uniquement.
- Évaluer une formulation séparant `fire`, `smoke`, `normal` si le besoin métier confirme ces états.

## Métriques et calibration

- Matrice de confusion, précision, rappel, F1 et PR-AUC par classe, sous-type et source.
- Taux de faux positifs explicite sur le corpus webcam négatif difficile.
- Seuil choisi sur validation selon un coût métier documenté, jamais sur le test.
- Temperature scaling sur un ensemble indépendant, avec ECE et reliability diagram.
- Politique d'abstention/hors domaine; le softmax seul ne détecte pas l'inconnu.

## Artefacts à sauvegarder

Sauvegarder ensemble : `state_dict`, architecture/version, classes et mapping, taille/interpolation, normalisation, version/hash dataset, split/graine, métriques, température de calibration et seuil opérationnel. Valider ces métadonnées au chargement.

## Critères d'acceptation

Le nouveau modèle ne remplace l'ancien que s'il améliore le rappel feu sans dépasser le taux maximal de faux positifs défini sur les scènes webcam externes, avec résultats reproductibles sur le test figé.
