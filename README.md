# Détection d’incendie — plateforme SaaS et intelligence artificielle

Ce dépôt regroupe un prototype complet de supervision des risques d’incendie pour des sites industriels. Il associe :

- une **plateforme web Laravel multi-usine** pour administrer les caméras, incidents, alertes, utilisateurs et abonnements ;
- une **application Python de détection** basée sur PyTorch, utilisable avec une image ou une webcam ;
- une **API FastAPI** pour l’inférence et la gestion d’un historique local ;
- un **dataset YOLO feu/fumée**, des poids YOLO 11 et des outils d’audit/préparation des données.

> **Avertissement important :** ce projet est un prototype académique. Le modèle peut produire des faux positifs, particulièrement sur des scènes hors domaine. Il ne remplace pas un détecteur, une alarme ou un dispositif de sécurité incendie certifié.

## Sommaire

- [Architecture générale](#architecture-générale)
- [Fonctionnalités](#fonctionnalités)
- [Technologies](#technologies)
- [Organisation du dépôt](#organisation-du-dépôt)
- [Prérequis](#prérequis)
- [Installation rapide](#installation-rapide)
- [Lancement](#lancement)
- [Configuration](#configuration)
- [API disponibles](#api-disponibles)
- [Modèles et données](#modèles-et-données)
- [Tests](#tests)
- [Sécurité](#sécurité)
- [Limites et points d’attention](#limites-et-points-dattention)
- [Documentation complémentaire](#documentation-complémentaire)

## Architecture générale

```text
Images / webcam                         Utilisateurs métier
       │                                       │
       ▼                                       ▼
Application Streamlit ──► CNN PyTorch      Dashboard Blade
       │                    │                  │
       ├── captures JPEG    │                  ▼
       └── historique SQLAlchemy          Laravel 12
                            │                  │
Client HTTP ───────────► FastAPI               ├── API REST authentifiée
                            │                  ├── gestion multi-usine
                            ▼                  └── alertes et audit
                       Base Python                 │
                                                  ▼
                                            Base Laravel
```

Les blocs Python et Laravel sont présents dans le même dépôt, mais ils ne sont pas encore reliés par un contrat d’intégration opérationnel. Ils possèdent notamment deux modèles de données différents pour les événements incendie. Consultez [Limites et points d’attention](#limites-et-points-dattention) avant de les lancer ensemble.

## Fonctionnalités

### Plateforme Laravel

- authentification web par session et API par jeton Bearer ;
- isolation des données par usine (architecture multi-tenant) ;
- gestion des usines, membres, utilisateurs, rôles et permissions ;
- gestion des plans et abonnements ;
- gestion des zones, caméras, seuils et informations de flux ;
- suivi de santé des caméras : statut, latence, FPS, CPU, GPU et mémoire ;
- suivi des détections de feu ou fumée et des médias associés ;
- cycle de vie des incidents : `detected`, `confirmed`, `false_alarm`, `resolved` ;
- alertes, acquittement, destinataires et notifications multicanal ;
- dashboard, graphiques, rapports et export ;
- paramètres par usine, clients API et journal d’audit ;
- endpoint de santé de la plateforme.

### Application Python

- classification d’une image JPG/PNG en `Fire` ou `No Fire` ;
- interface Streamlit pour l’upload, la webcam et l’historique ;
- capture webcam locale avec OpenCV ;
- confirmation d’un événement après plusieurs images positives ;
- seuil de confiance et délai anti-doublon configurables ;
- sauvegarde locale de la capture brute ;
- historique MySQL via SQLAlchemy ;
- API FastAPI avec documentation Swagger ;
- diagnostic détaillé des prédictions et scripts d’audit du dataset.

## Technologies

| Domaine | Technologies principales |
|---|---|
| Plateforme web | PHP 8.2+, Laravel 12, Blade, Eloquent |
| Interface | Tailwind CSS 4, Vite 7, JavaScript, Axios |
| API IA | Python 3.10–3.12, FastAPI, Uvicorn |
| Interface IA | Streamlit, OpenCV, Pillow |
| Machine learning | PyTorch, Torchvision, Ultralytics/poids YOLO 11 |
| Données | MySQL, SQLAlchemy, PyMySQL |
| Tests | PHPUnit 11, `unittest` Python |

## Organisation du dépôt

```text
detect_incendie-app/
├── backend/                       # Application Laravel
│   ├── app/                       # Modèles, contrôleurs, services et policies
│   ├── database/                  # Migrations, factories et seeders
│   ├── resources/                 # Vues Blade, CSS et JavaScript
│   ├── routes/                    # Routes web, API et plateforme
│   ├── tests/                     # Tests unitaires et fonctionnels
│   ├── .env.example               # Configuration Laravel de référence
│   ├── composer.json              # Dépendances et scripts PHP
│   └── package.json               # Dépendances et scripts front-end
├── Python/                        # Moteur de détection et données
│   ├── api.py                     # API FastAPI
│   ├── app.py                     # Interface Streamlit
│   ├── config.py                  # Configuration d’environnement
│   ├── model.py                   # Architecture CNN_Bottleneck
│   ├── services/                  # Inférence, prétraitement, webcam
│   ├── repositories/              # Accès aux événements SQLAlchemy
│   ├── database/schema.sql        # Schéma autonome de l’application Python
│   ├── tests/                     # Tests Python
│   ├── scripts/                   # Audit et préparation du dataset
│   ├── fire_factory_dataset/      # Dataset YOLO feu/fumée
│   ├── fire_cnn_bottleneck.pth    # Checkpoint utilisé par l’application
│   ├── yolo11{n,s,m}.pt           # Poids YOLO de départ
│   └── requirements.txt           # Dépendances Python
└── README.md
```

Les répertoires de dataset et d’exécution contiennent plusieurs milliers de fichiers. Ils ne sont pas nécessaires pour exécuter le classifieur CNN déjà entraîné, mais servent à l’audit et au réentraînement expérimental.

## Prérequis

- Git ;
- PHP 8.2 ou supérieur avec les extensions requises par Laravel et MySQL ;
- Composer ;
- Node.js et npm ;
- MySQL 8 recommandé ;
- Python 3.10 à 3.12 ;
- une webcam autorisée par Windows pour la détection en temps réel.

Le classifieur fonctionne sur CPU. CUDA est utilisé automatiquement par PyTorch lorsqu’un environnement compatible est disponible.

## Installation rapide

### 1. Plateforme Laravel

Depuis la racine du dépôt :

```powershell
cd backend
Copy-Item .env.example .env
composer install
npm install
php artisan key:generate
```

Créez une base MySQL dédiée, par exemple `fire_detection_saas`, puis adaptez les variables `DB_*` dans `backend/.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fire_detection_saas
DB_USERNAME=root
DB_PASSWORD=votre_mot_de_passe
```

Initialisez ensuite le schéma et les données de démonstration :

```powershell
php artisan migrate --seed
php artisan storage:link
npm run build
```

Le script Composer suivant regroupe l’installation, la génération de clé, les migrations et le build front-end, mais suppose que MySQL est déjà configuré :

```powershell
composer run setup
```

### 2. Application Python

Dans un autre terminal, depuis la racine :

```powershell
cd Python
python -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip setuptools wheel
python -m pip install -r requirements.txt
Copy-Item .env.example .env
```

Créez une seconde base, par exemple `fire_detection_ai`, puis modifiez `Python/.env` :

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=fire_detection_ai
DB_USER=root
DB_PASSWORD=votre_mot_de_passe
```

Créez la base vide dans MySQL. SQLAlchemy créera la table au démarrage, ou adaptez puis exécutez `Python/database/schema.sql` avec le nom de cette base.

## Lancement

### Laravel

Pour lancer le serveur, le worker de queue, les logs et Vite ensemble :

```powershell
cd backend
composer run dev
```

Ou lancez uniquement le serveur web et les assets dans deux terminaux :

```powershell
php artisan serve
npm run dev
```

- application Laravel : <http://127.0.0.1:8000>
- contrôle Laravel natif : <http://127.0.0.1:8000/up>
- santé applicative : <http://127.0.0.1:8000/api/health>

### Streamlit

```powershell
cd Python
.\.venv\Scripts\Activate.ps1
python -m streamlit run app.py
```

Interface : <http://localhost:8501>

La webcam est ouverte par `cv2.VideoCapture(0)` sur la machine qui exécute Streamlit. Sur un serveur distant, elle ne correspond donc pas à la caméra du navigateur.

### FastAPI

```powershell
cd Python
.\.venv\Scripts\Activate.ps1
python -m uvicorn api:app --reload
```

- API : <http://127.0.0.1:8000>
- Swagger : <http://127.0.0.1:8000/docs>

Laravel et FastAPI utilisent tous deux le port `8000` par défaut. Pour les exécuter simultanément, attribuez un autre port à FastAPI :

```powershell
python -m uvicorn api:app --reload --port 8001
```

## Configuration

### Variables Laravel importantes

| Variable | Rôle | Valeur d’exemple |
|---|---|---|
| `APP_URL` | URL publique de Laravel | `http://localhost:8000` |
| `APP_DEBUG` | Affichage des erreurs détaillées | `true` en local |
| `DB_*` | Connexion à la base Laravel | MySQL local |
| `QUEUE_CONNECTION` | Backend de file d’attente | `database` |
| `SESSION_DRIVER` | Stockage des sessions | `database` |
| `MAIL_MAILER` | Envoi des notifications email | `log` en local |
| `API_TOKEN_EXPIRATION` | Durée du jeton en minutes | `10080` |
| `DEMO_PASSWORD` | Mot de passe des comptes de démo | à remplacer |

### Variables Python importantes

| Variable | Rôle | Défaut |
|---|---|---|
| `DB_HOST`, `DB_PORT` | Serveur MySQL | `127.0.0.1:3306` |
| `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Base et identifiants Python | requis |
| `FIRE_CONFIDENCE_THRESHOLD` | Seuil général de classification | `0.95` |
| `FIRE_EVENT_THRESHOLD` | Seuil de création webcam | `0.95` |
| `REQUIRED_POSITIVE_FRAMES` | Images positives consécutives | `3` |
| `DETECTION_COOLDOWN_SECONDS` | Délai anti-doublon | `15` |
| `PREDICTION_DEBUG` | Logs détaillés de l’inférence | `false` |

Ne versionnez jamais les fichiers `.env` ni des secrets réels.

## Comptes de démonstration Laravel

Le seeder principal crée deux usines (`Atlas Manufacturing Casablanca` et `Nord Industries Tanger`) et les comptes suivants :

| Rôle | Adresse |
|---|---|
| Super administrateur | `superadmin@firewatch.demo` |
| Administrateur plateforme | `platform@firewatch.demo` |
| Propriétaire d’usine | `owner@firewatch.demo` |
| Responsable d’usine | `manager@firewatch.demo` |
| Responsable sécurité | `safety@firewatch.demo` |
| Agent de sécurité | `security@firewatch.demo` |
| Agent de maintenance | `maintenance@firewatch.demo` |
| Lecteur | `viewer@firewatch.demo` |

Le mot de passe commun provient de `DEMO_PASSWORD`. Changez impérativement sa valeur avant tout environnement partagé.

## API disponibles

### FastAPI

| Méthode | Route | Description |
|---|---|---|
| `GET` | `/health` | État du modèle et de MySQL |
| `POST` | `/predict` | Prédiction sur une image JPG/PNG, 10 Mo maximum |
| `GET` | `/events` | Historique filtrable et paginé |
| `GET` | `/events/{id}` | Détail d’un événement |
| `PATCH` | `/events/{id}/status` | Mise à jour du statut |
| `PATCH` | `/events/{id}/alert` | Mise à jour du marqueur d’alerte |

Exemple PowerShell, avec FastAPI sur le port 8001 :

```powershell
curl.exe -F "image=@test_feu.jpg;type=image/jpeg" http://127.0.0.1:8001/predict
```

L’API FastAPI autorise actuellement toutes les origines CORS et ne protège pas ses routes par authentification. Cette configuration convient uniquement au développement local.

### Laravel

Les routes d’authentification sont sous `/api/auth` : inscription, connexion, mot de passe oublié/réinitialisé, profil, rafraîchissement et déconnexion.

Les routes de plateforme incluent notamment :

- `GET /api/health` ;
- `GET /api/factory-context` ;
- `/api/factories/{factory}/dashboard/*` ;
- `GET|PATCH /api/factories/{factory}/settings` ;
- `GET /api/factories/{factory}/audit-logs` ;
- `POST /api/factories/{factory}/events/{event}/media`.

Les routes protégées nécessitent un jeton Bearer. Les routes liées à une usine vérifient aussi le contexte du tenant. La description détaillée se trouve dans `backend/API_DOCUMENTATION.md`.

## Modèles et données

### Classifieur utilisé en production locale

L’application Streamlit et FastAPI charge `Python/fire_cnn_bottleneck.pth`. Son architecture `CNN_Bottleneck` produit deux classes :

| Index | Classe |
|---:|---|
| `0` | `fire` |
| `1` | `no_fire` |

Les images sont redimensionnées en `224 × 224` et normalisées avec les statistiques ImageNet. `Python/model_metadata.json` est la source de vérité pour l’ordre des classes, l’architecture et le prétraitement.

### Dataset YOLO

`Python/fire_factory_dataset/` est un dataset de détection d’objets séparé en `train`, `val` et `test`, avec deux classes :

| Index | Classe |
|---:|---|
| `0` | `fire` |
| `1` | `smoke` |

Le résumé de préparation annonce 20 309 ressources actives, 259 exclues et une séparation contrôlée contre certaines fuites de données. Les scripts d’audit et de reconstruction sont placés dans `Python/scripts/`. Les poids `yolo11n.pt`, `yolo11s.pt` et `yolo11m.pt` servent de points de départ expérimentaux ; ils ne sont pas chargés par `app.py` ou `api.py`.

## Tests

### Laravel

```powershell
cd backend
composer test
```

La suite couvre notamment l’authentification API, le contexte multi-tenant, les permissions, le dashboard, les paramètres, l’audit, les clients API et les comptes de démonstration.

### Python

```powershell
cd Python
.\.venv\Scripts\Activate.ps1
python -m unittest discover -s tests -v
python -m compileall .
```

Les tests utilisent des doubles et SQLite en mémoire pour les opérations de persistance ; ils n’ouvrent pas la webcam réelle.

## Sécurité

Le backend Laravel met en œuvre :

- des jetons API hachés SHA-256, révocables et expirables ;
- le contrôle d’accès par rôles, permissions et policies ;
- l’isolation des requêtes par `factory_id` ;
- la validation par Form Requests et la protection CSRF ;
- le chiffrement des informations sensibles de caméra ;
- le masquage des secrets et la journalisation d’audit ;
- une limitation de débit distincte pour l’authentification et l’API.

Avant une mise en production : désactivez `APP_DEBUG`, imposez HTTPS, configurez un vrai fournisseur d’email/notification, restreignez CORS, ajoutez une authentification à FastAPI, gérez les secrets hors du dépôt et définissez une politique de sauvegarde/rétention des images.

## Limites et points d’attention

1. **Schémas de base incompatibles.** Laravel et Python créent tous deux `fire_events`, mais avec des colonnes et types incompatibles. Utilisez deux bases distinctes (`fire_detection_saas` et `fire_detection_ai`) jusqu’à la création d’une intégration explicite.
2. **Pas de synchronisation IA → SaaS.** Le moteur Python n’envoie actuellement pas ses détections à l’API Laravel. L’endpoint Laravel existant ajoute un média à un événement déjà créé ; il ne constitue pas encore un endpoint d’ingestion complet.
3. **Collision de ports.** Laravel et FastAPI écoutent par défaut sur `8000`. Utilisez par exemple `8001` pour FastAPI.
4. **Modèles distincts.** Le CNN binaire réellement utilisé par les interfaces n’est pas le pipeline YOLO feu/fumée fourni avec le dataset.
5. **Faux positifs connus.** Le checkpoint CNN peut être très confiant sur des scènes qui ne ressemblent pas aux données d’entraînement. Les seuils et la confirmation multi-frame réduisent le bruit, sans corriger le modèle.
6. **API Python ouverte.** FastAPI n’a pas encore d’authentification et son CORS est permissif.
7. **Webcam locale uniquement.** L’interface ne consomme pas encore les flux RTSP configurés dans Laravel.
8. **Dépôt volumineux.** Dataset, checkpoints et résultats d’exécution sont présents dans Git ; Git LFS ou un stockage d’artefacts est préférable à terme.
9. **Notifications non finalisées.** Le backend structure les notifications, mais l’environnement fourni utilise le driver email `log`.

## Dépannage

- **MySQL `Access denied`** : vérifiez l’utilisateur, le mot de passe et les droits sur la base concernée.
- **Connexion refusée** : démarrez MySQL et vérifiez le port `3306`.
- **Webcam noire ou indisponible** : autorisez la caméra dans Windows, fermez Teams/Zoom et vérifiez l’index OpenCV.
- **Erreur DLL OpenCV** : mettez Windows à jour et installez Microsoft Visual C++ Redistributable x64.
- **Activation PowerShell interdite** : exécutez directement `.\.venv\Scripts\python.exe -m streamlit run app.py`.
- **Assets Laravel absents** : exécutez `npm install`, puis `npm run build` ou `npm run dev`.
- **Images Laravel inaccessibles** : exécutez `php artisan storage:link`.

## Documentation complémentaire

- `backend/README.md` : description détaillée de la plateforme Laravel ;
- `backend/API_DOCUMENTATION.md` : contrat de l’API Laravel ;
- `backend/BLADE_DASHBOARD_ARCHITECTURE.md` : architecture du dashboard ;
- `backend/DEMO_ACCOUNTS.md` : comptes et données de démonstration ;
- `Python/README.md` : utilisation détaillée de Streamlit, FastAPI et de la webcam ;
- `Python/RUN_PROJECT_WINDOWS.md` : commandes Windows ;
- `Python/PROJECT_AUDIT.md` : audit du moteur Python ;
- `Python/FALSE_POSITIVE_AUDIT.md` : analyse des faux positifs ;
- `Python/MODEL_RETRAINING_PLAN.md` : plan de réentraînement ;
- `Python/fire_factory_dataset/README_DATASET.md` : documentation du dataset.

## Statut du projet

Le dépôt fournit une base fonctionnelle pour démonstration, expérimentation et développement. Pour obtenir un système intégré, la prochaine étape structurante consiste à définir un endpoint Laravel d’ingestion des détections, puis à faire envoyer par Python les identifiants d’usine/caméra, la classe, la confiance, les boîtes de détection et les médias avec une authentification machine-à-machine.
