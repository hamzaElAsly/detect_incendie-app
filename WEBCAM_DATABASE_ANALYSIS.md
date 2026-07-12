# Analyse webcam et base de données

## Fonctionnement actuel

Le projet réalise une classification binaire d'images avec `CNN_Bottleneck` et les poids existants `fire_cnn_bottleneck.pth`. L'ordre des dossiers utilisé lors de l'entraînement (`ImageFolder`) impose les classes `Fire = 0` et `No_Fire = 1`. Les deux interfaces convertissent l'image en RGB, la redimensionnent en 224×224, la transforment en tenseur et appliquent la normalisation ImageNet. La confiance affichée est le maximum du softmax sur les deux sorties.

`app.py` fournit l'upload JPG/PNG Streamlit. Son seuil d'incertitude visuel est 0,60, sans seuil spécifique d'enregistrement. `api.py` fournit `/`, `/health` et `/predict`; une prédiction `Fire` est déclarée forte à partir de 0,85. Les deux fichiers chargent séparément le même modèle et dupliquent transformations, labels et inférence. `model.py` définit seulement l'architecture PyTorch. Il n'existe actuellement ni webcam, ni persistance, ni historique, ni statut d'événement ou d'alerte.

## Fichiers à modifier

- `app.py` : conserver l'upload et ajouter navigation, webcam et historique.
- `api.py` : conserver `/predict` et ajouter santé et CRUD métier.
- `requirements.txt`, `.gitignore`, `README.md` : dépendances, secrets et documentation.

## Fichiers à créer

- `.env.example`, `config.py`, `database.py`, `logging_config.py`.
- `models/fire_event.py`, `repositories/fire_event_repository.py`.
- `services/fire_detection_service.py`, `services/capture_service.py`, `services/detection_gate.py`.
- `database/schema.sql`, `storage/detections/`, `logs/`.
- tests unitaires et d'intégration sans webcam réelle.
- `WEBCAM_DATABASE_IMPLEMENTATION_REPORT.md`.

## Risques détectés

- La confiance softmax n'est pas calibrée et le modèle est un classifieur global, pas un détecteur localisant les flammes.
- La taxonomie d'entraînement inclut la fumée dans `Fire` et des feux artificiels dans `No_Fire`.
- MySQL ou la webcam peuvent être absents; ces pannes ne doivent pas arrêter toute l'application.
- La boucle Streamlit doit libérer `VideoCapture` lors d'un arrêt ou d'une erreur.
- Sans consolidation et cooldown, une même scène générerait un événement par frame.
- Une capture réussie suivie d'une panne MySQL peut laisser un fichier orphelin; l'incident doit être journalisé localement.
- Les versions PyTorch actuelles sont volumineuses et l'installation dépend de l'environnement Windows.

## Plan d'implémentation

1. Centraliser configuration, logs, modèle et inférence, en réutilisant strictement les poids existants.
2. Ajouter SQLAlchemy/MySQL, le modèle `fire_events`, le dépôt et le schéma SQL manuel.
3. Ajouter stockage relatif des captures et une porte de confirmation (seuil 0,80, 3 frames, cooldown 15 s, configurables).
4. Étendre Streamlit avec upload préservé, webcam robuste et historique modifiable.
5. Étendre FastAPI avec les routes événements validées par Pydantic.
6. Ajouter tests, documentation, installation et validations disponibles, puis consigner les limites matérielles.
