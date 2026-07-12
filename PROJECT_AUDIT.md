# Audit du projet

Audit réalisé le 11 juillet 2026 sous Windows, sans suppression de données, modification des poids ni réentraînement.

## 1. Résumé du projet

Le dépôt réalise une classification binaire d'images liées au feu. Le même `state_dict` PyTorch alimente Streamlit et FastAPI. Le notebook retrace l'exploration, plusieurs expériences de réduction de dimension et l'entraînement du CNN final.

## 2. Structure des fichiers

|         Élément           |                 Rôle                | État |
|---------------------------|-------------------------------------|---------------------------|
| `app.py`                  | interface Streamlit                 | fonctionnelle             |
| `api.py`                  | API FastAPI                         | fonctionnelle             |
| `index.html`              | client statique de l'API, sans backend propre | contrat corrigé |
| `model.py`                | architecture CNN d'inférence        | cohérente avec les poids  |
| `Explory.ipynb`           | exploration/entraînement/évaluation | historique, non requis à l'exécution |
| `fire_cnn_bottleneck.pth` | poids `state_dict` PyTorch          | 2 475 461 octets, SHA-256 `090ABAAC39E1D6A09224B81B975777ABFBC659E391A47AE007236B3BA221EFB4`|
| `data_set`                | 1 112 PNG, deux classes équilibrées | aucune image corrompue    |

Images d'exemple à la racine : `test_feu.jpg`, `image_bougie.jpg` et cinq PNG. Les cinq PNG sont des copies exactes d'images du dataset ; elles sont conservées comme jeux de démonstration. Le notebook référence `Fire (235).png`, absent à la racine mais présent dans `data_set/Fire` : ce chemin est fragile si la cellule est rejouée.

## 3. Technologies utilisées

Python, PyTorch/torchvision, Pillow, NumPy, scikit-learn, Streamlit, FastAPI/Uvicorn, HTML/CSS/JavaScript. Le notebook utilise aussi Matplotlib et Seaborn pour les visualisations, absents des dépendances d'exécution car le notebook n'est pas requis pour servir le modèle.

Les interfaces sont distinctes : Streamlit exécute directement le modèle ; FastAPI expose du JSON ; `index.html` appelle FastAPI ; le notebook explore, entraîne et évalue mais n'est pas une application de production.

## 4. Fonctionnement du modèle

`CNN_Bottleneck` contient trois convolutions (3→32→64→128, stride 2), un pooling adaptatif 8×8, un flatten, une couche 8192→64 et un classifieur 64→2. Le checkpoint est un `OrderedDict` de 10 paramètres. Les formes `(64, 8192)` et `(2, 64)` confirment `bottleneck_dim=64` et deux sorties.

Les labels proviennent de l'ordre alphabétique d'`ImageFolder` : `Fire=0`, `No_Fire=1`. Prétraitement commun : RGB, resize 224×224, tenseur, normalisation ImageNet. Il correspond au pipeline de validation/inférence du notebook.

## 5. Dépendances détectées

Imports applicatifs directs : `fastapi`, `uvicorn` (serveur), `torch`, `torchvision`, `PIL`, `streamlit`, `python-multipart`. `numpy` et `scikit-learn` sont utilisés dans le notebook. Versions validées : FastAPI 0.139.0, Uvicorn 0.51.0, torch 2.13.0 CPU, torchvision 0.28.0 CPU, Pillow 12.3.0, NumPy 2.5.1, scikit-learn 1.9.0, python-multipart 0.0.32 et Streamlit 1.59.1.

## 6. Dépendances ajoutées ou supprimées

`streamlit` manquait et a été ajouté. Aucune dépendance existante n'a été supprimée. Les neuf dépendances directes ont été figées aux versions réellement installées et testées. PyTorch a été installé depuis l'index officiel PyPI ; la roue obtenue est CPU (`2.13.0+cpu`), adaptée à la machine où CUDA n'est pas disponible.

## 7. Problèmes trouvés

- poids chargés via un chemin dépendant du répertoire courant ;
- `streamlit` absent de `requirements.txt` ;
- API acceptant tout MIME `image/*`, sans contrôle du contenu, de taille ni gestion Pillow ;
- exception 500 possible sur fichier vide/corrompu ;
- HTML attendant un objet `prediction` imbriqué et des `probabilities`, contrairement à l'API ;
- HTML interrogeant `/health`, route absente ;
- URL HTML exclusivement distante, gênante en local ;
- mojibake visible dans les sources lors de leur lecture par certains outils/encodages ;
- CORS ouvert à toutes les origines : pratique pour le HTML statique, trop large pour une production sensible ;
- réponse API incluant l'image complète en base64, coûteuse en mémoire/bande passante ;
- seuil `confidence < 0.5` impossible pour le maximum d'un softmax binaire ; le statut `risk` mélangeait confiance et risque incendie ;
- absence d'authentification, rate limiting, logs structurés et tests automatisés ;
- métriques du notebook issues de splits aléatoires et entraînements successifs, sans reproductibilité forte ;
- VAE du notebook crée des couches après l'optimiseur, donc celles-ci ne sont pas correctement enregistrées dans l'optimiseur ;
- le notebook importe Matplotlib/Seaborn mais ces outils ne sont pas requis par les applications ;
- `py` est installé mais ne détecte aucun Python, et seul Python 3.12.10 est utilisable sur la machine ;
- Git signale une propriété de dépôt douteuse sous le compte sandbox ; aucune configuration globale n'a été modifiée.

## 8. Corrections effectuées

- chemins du modèle basés sur `Path(__file__).resolve().parent` ;
- chargement sécurisé `weights_only=True`, CPU, modèle mis en `eval()` et mis en cache dans Streamlit ;
- validation JPG/PNG, contenu Pillow, fichier vide et limite de 10 Mo dans FastAPI ;
- gestion d'image invalide dans Streamlit ;
- route `/health` ;
- contrat HTML aligné et sélection automatique de l'API locale ;
- statut API corrigé pour qu'une prédiction `No Fire` ne produise pas une alerte de risque ;
- dépendances reproductibles, `.gitignore`, README et guide Windows.

## 9. Commandes exécutées

Inventaire avec `rg --files`, lectures PowerShell, inspection JSON du notebook, comptages et SHA-256 ; `python --version`, `py --version`, `py -0p`, `pip --version`, `git --version` ; création par `python -m venv .venv` (fallback car `py -3.11` indisponible) ; mise à jour pip/setuptools/wheel ; `pip install -r requirements.txt` ; `compileall` ; tests d'imports et d'inférence ; Uvicorn, requêtes HTTP/curl ; Streamlit et healthcheck. La première installation sans accès réseau a échoué proprement, puis a réussi avec l'accès autorisé. Un premier démarrage automatisé via `Start-Process` a rencontré le conflit Windows `PATH/Path`; le test a été relancé avec un job PowerShell.

## 10. Résultats des tests

- Python 3.12.10, pip 26.1.2 ; syntaxe et imports : OK ;
- 1 112/1 112 images du dataset valides ;
- checkpoint chargé sur CPU sans clé manquante ;
- `test_feu.jpg` : entrée `(1, 3, 224, 224)`, sortie `(1, 2)`, `Fire`, confiance 0,957946 ;
- `image_bougie.jpg` : `Fire`, confiance 0,538931 (incertain) ;
- `Safe_Fire (3).png` : `No Fire`, confiance 0,991397 ;
- Streamlit : health et page HTTP 200, modèle chargé au démarrage, serveur arrêté ;
- FastAPI : `/`, `/health`, `/docs`, `/openapi.json` HTTP 200 ; prédiction valide HTTP 200 ; mauvais MIME et faux PNG HTTP 400 ; serveur arrêté ;
- métriques historiques du notebook : accuracy 0,9345, précision 0,9222, rappel 0,9540, F1 0,9379 ; accuracy bruitée 0,6845.

L'upload Streamlit interactif n'a pas été piloté dans un navigateur, mais son pipeline exact a été exercé directement sur trois images et le serveur a chargé le modèle sans erreur.

## 11. Fonctionnalités qui marchent

Chargement CPU, inférence, upload Streamlit (code et pipeline validés), API JSON, Swagger, contrôles d'upload, client HTML aligné, chemins Windows portables et dataset lisible.

## 12. Fonctionnalités qui ne marchent pas ou restent non prouvées

Le notebook n'est pas garanti exécutable de bout en bout sans ajouter ses dépendances de visualisation et corriger ses cellules expérimentales ; il n'a volontairement pas été réexécuté afin de ne pas réentraîner. Les valeurs de monitoring HTML sont des simulations. Aucun test navigateur end-to-end du HTML/Streamlit n'a été réalisé.

## 13. Risques techniques

Les labels `Smoke→Fire` et `Artificial_Fire→No_Fire` peuvent ne pas correspondre au besoin réel. Une confiance softmax n'est pas une probabilité calibrée de danger. CORS ouvert, absence de rate limiting et base64 exposent l'API à l'abus. Un modèle entraîné sur seulement 1 112 images ne doit pas remplacer un détecteur certifié.

## 14. Recommandations

Valider la taxonomie avec le métier, séparer un jeu de test figé sans quasi-doublons, tester le modèle par sous-type, calibrer le seuil sur la probabilité `Fire`, restreindre CORS en production, rendre la limite configurable et ajouter tests unitaires/intégration.

## 15. Plan d'amélioration priorisé

1. Clarifier labels, seuils et cas d'usage de sécurité.
2. Ajouter tests automatisés du modèle, des routes et des fichiers invalides.
3. Construire un split reproductible et auditer les doublons visuels/fuites.
4. Corriger et isoler le notebook d'expérimentation avec ses dépendances dédiées.
5. Durcir le déploiement (CORS, limite de requêtes, logs, suppression optionnelle du base64).
6. Envisager ensuite seulement caméra, alertes, base de données ou nouveau design.
