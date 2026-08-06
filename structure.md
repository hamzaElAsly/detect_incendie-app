# Structure du projet detect_incendie-app

## Vue d'ensemble

Ce dépôt combine deux applications principales :

- `backend/` : une application Laravel 12 multi-usine pour la gestion des caméras, incidents, alertes, utilisateurs, abonnements et rapports.
- `Python/` : un moteur de détection d'incendie basé sur PyTorch avec une interface Streamlit, une API FastAPI, un historique SQLAlchemy/MySQL et des outils d'audit de dataset.

Le dépôt contient également des documents de reporting, des audits, et des ressources de dataset et de modèle.

## Arborescence principale

- `.gitignore`
- `README.md`
- `backend/`
- `Python/`

## backend/

Application Laravel, composée de :

- `app/`
  - `Actions/`
  - `Authorization/`
  - `Domain/`
  - `Enums/`
  - `Http/`
  - `Models/`
  - `Policies/`
  - `Providers/`
  - `Services/`
- `bootstrap/`
  - `app.php`
  - `providers.php`
  - `cache/`
- `config/`
  - `app.php`, `auth.php`, `cache.php`, `camera_health.php`, `cors.php`, `database.php`, `filesystems.php`, `logging.php`, `mail.php`, `queue.php`, `services.php`, `session.php`
- `database/`
  - `factories/`
  - `migrations/`
  - `seeders/`
- `edge-agent/` (documentations relatives aux intégrations Edge)
- `lang/`
  - `en/`
  - `fr/`
- `public/`
  - `index.php`, `robots.txt`, `build/`, `hot`
- `resources/`
  - `css/`, `js/`, `views/`
- `routes/`
  - `api.php`
  - `console.php`
  - `platform.php`
  - `web.php`
- `storage/`
  - `app/`, `framework/`, `logs/`
- `tests/`
  - `Feature/`, `Unit/`, `TestCase.php`
- `vendor/`
- fichiers de configuration et scripts : `artisan`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `phpunit.xml`, `vite.config.js`

### Backend : fonctionnalités clés

- Authentification web et API Bearer
- Gestion multi-tenant par usine
- Gestion des utilisateurs, rôles et permissions
- Gestion des caméras, zones, seuils et flux
- Suivi de santé des caméras
- Gestion des événements de feu/fumée et des incidents
- Workflow d'incident (`detected`, `confirmed`, `false_alarm`, `resolved`)
- Notifications, avis, destinataires, alertes et suivi
- Interface Blade + Tailwind + Vite
- Tests PHPUnit et scripts d'installation

## Python/

Application de détection et API. Contenu principal :

- `api.py` : API FastAPI pour la prédiction d'image et la gestion des événements
- `app.py` : interface Streamlit pour upload, webcam et historique
- `config.py` : chargement de la configuration depuis `.env`
- `database.py` : configuration SQLAlchemy, dépendance de session et initialisation
- `logging_config.py` : configuration du logging
- `model.py` : architecture du modèle CNN ou composant de chargement de modèle
- `services/`
  - `capture_service.py`
  - `detection_gate.py`
  - `fire_detection_service.py`
  - `image_preprocessing.py`
- `repositories/`
  - `fire_event_repository.py`
- `models/`
  - définition des entités SQLAlchemy et des statuts d'événement
- `configs/`
- `database/`
  - `schema.sql`
- `data_set/`
- `fire_factory_dataset/`
- `models/`
- `reports/`
- `runs/`
- `scripts/`
- `storage/`
- `tests/`
- fichiers de dépendances : `requirements.txt`
- fichiers de modèles poids : `fire_cnn_bottleneck.pth`, `yolo11m.pt`, `yolo11n.pt`, `yolo11s.pt`
- `model_metadata.json`

### Python : fonctionnalités clés

- Inférence image (`predict_pil`) et webcam OpenCV
- Streamlit pour interface utilisateur locale
- FastAPI pour API REST et documentation Swagger
- Historique d'événements stocké dans MySQL via SQLAlchemy
- Gestion du cooldown de détection et du seuil de confiance
- Audit du dataset et diagnostics de prédiction
- Scripts d'analyse et de réentraînement

## Points d'intégration et observations

- Les deux applications coexistent dans le même dépôt, mais aucune intégration opérationnelle explicite n'est visible entre
  `backend/` et `Python/`.
- Le backend Laravel expose une API et un dashboard métier ; le dossier Python fournit une API d'inférence et une interface locale.
- Les deux applications utilisent MySQL, mais avec des schémas et des modèles de données séparés.
- `backend/` est plutôt conçu comme plateforme SaaS multi-tenant ; `Python/` est conçu comme moteur local de détection IA.

## Fichiers de configuration importants

- `backend/.env.example` : configuration Laravel
- `Python/.env.example` : configuration MySQL et seuils d'inférence
- `backend/composer.json` : dépendances PHP
- `backend/package.json` : configuration front-end et scripts Vite
- `Python/requirements.txt` : dépendances Python

## Notes d'utilisation rapide

- Pour Laravel : `cd backend`, `composer install`, `npm install`, `php artisan migrate --seed`, `npm run build`.
- Pour Python : `cd Python`, `python -m venv .venv`, `.\.venv\Scripts\Activate.ps1`, `python -m pip install -r requirements.txt`, `python -m streamlit run app.py`, `python -m uvicorn api:app --reload`.

## Recommandations générales

- Séparer clairement les responsabilités et documenter les points d'intégration entre la plateforme SaaS et le moteur de détection IA.
- Vérifier que les schémas de données ne divergent pas si une intégration backend/Python est prévue.
- Exclure `Python/.venv/` du dépôt si ce n'est pas déjà le cas dans `.gitignore`.
