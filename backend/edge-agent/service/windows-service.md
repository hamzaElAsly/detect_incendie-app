# Service Windows

Créer un compte de service sans privilèges administrateur, installer les dépendances dans `.venv`, puis utiliser NSSM ou le planificateur de tâches pour exécuter `.venv\Scripts\python.exe -m src.main` depuis le dossier `edge-agent`. Protéger `.env` par ACL, activer le redémarrage sur échec et ne jamais placer le token dans la ligne de commande.
