# Détection d'incendie — image, webcam et événements

Ce projet utilise le classifieur PyTorch existant `fire_cnn_bottleneck.pth` pour distinguer `Fire` (classe 0) de `No_Fire` (classe 1). Il propose l'upload d'image, une surveillance webcam OpenCV, un historique MySQL et une API FastAPI. Aucun réentraînement n'est nécessaire.

## Fonctionnalités

- upload JPG/PNG Streamlit et `POST /predict` conservés ;
- webcam locale avec `cv2.VideoCapture(0)`, annotation et arrêt propre ;
- confirmation après plusieurs frames, seuil de confiance et cooldown ;
- captures JPEG sous `storage/detections` avec chemin relatif en base ;
- événements MySQL, statuts `detected`, `confirmed`, `false_alarm`, `resolved` et suivi manuel `alert_sent` ;
- historique filtrable et routes FastAPI paginées.

## Prérequis Windows

Python 3.10 à 3.12, une webcam autorisée dans **Paramètres > Confidentialité et sécurité > Caméra**, et MySQL Server 8 recommandé. Le modèle fonctionne sur CPU; CUDA est utilisé automatiquement s'il est disponible.

```powershell
python -m venv .venv
.venv\Scripts\activate
python -m pip install -r requirements.txt
```

OpenCV est installé par `opencv-python`. En cas d'erreur DLL, mettez Windows à jour et installez le Microsoft Visual C++ Redistributable x64. Fermez Teams/Zoom si la caméra est occupée.

## Configuration MySQL

Dans MySQL Workbench ou le client `mysql`, exécutez `database/schema.sql`. Il crée la base `fire_detection` et la table `fire_events`. Copiez ensuite `.env.example` vers `.env` et adaptez les valeurs :

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=fire_detection
DB_USER=root
DB_PASSWORD=votre_mot_de_passe
FIRE_CONFIDENCE_THRESHOLD=0.80
DETECTION_COOLDOWN_SECONDS=15
REQUIRED_POSITIVE_FRAMES=3
```

`.env` est ignoré par Git. Le mot de passe n'est jamais écrit dans les logs. SQLAlchemy crée aussi la table au démarrage si la base existe et si le compte possède les droits nécessaires.

La table contient `id`, `detected_at`, `image_path`, `status`, `alert_sent`, `confidence`, `source`, `created_at` et `updated_at`.

## Lancement

```powershell
.venv\Scripts\activate
python -m unittest discover -s tests -v
python -m streamlit run app.py
python -m uvicorn api:app --reload
```

- Streamlit : <http://localhost:8501>
- FastAPI : <http://localhost:8000>
- Swagger : <http://localhost:8000/docs>

Routes : `GET /health`, `POST /predict`, `GET /events`, `GET /events/{id}`, `PATCH /events/{id}/status` et `PATCH /events/{id}/alert`. `/events` accepte `status`, `alert_sent`, `source`, `limit` et `offset`.

## Logique webcam

Une frame est positive lorsque le modèle prédit `Fire` avec une confiance d'au moins 0,80. Trois frames positives consécutives sont requises. Une fois un événement créé, le cooldown de 15 secondes interdit un nouvel enregistrement; la séquence doit ensuite être confirmée à nouveau. Ces trois valeurs sont configurables dans `.env`.

Lors d'une confirmation, l'image est d'abord sauvegardée sous `storage/detections/fire_YYYYMMDD_HHMMSS_microseconds.jpg`, puis l'événement est inséré. Si MySQL est indisponible, l'interface reste active, la capture est conservée et l'erreur est consignée dans `logs/app.log`.

## Tests

Les tests utilisent SQLite en mémoire et des objets simulés; ils n'ouvrent jamais la webcam réelle.

```powershell
python -m unittest discover -s tests -v
python -m compileall .
python -c "import cv2; import torch; import torchvision; import sqlalchemy; import pymysql; import streamlit; import fastapi"
```

## Problèmes fréquents

- `Access denied`: vérifier `DB_USER`, `DB_PASSWORD` et les droits MySQL.
- `Can't connect`: démarrer le service Windows MySQL et vérifier le port 3306.
- caméra noire/inaccessible: vérifier les permissions, fermer les applications concurrentes et tester un autre index OpenCV si la caméra n'est pas l'index 0.
- Streamlit exécuté sur un serveur distant: `VideoCapture(0)` ouvre la caméra du serveur, pas celle du navigateur.
- le modèle est un classifieur expérimental; il ne remplace pas un système incendie certifié.
