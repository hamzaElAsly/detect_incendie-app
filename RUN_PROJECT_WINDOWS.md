# Lancer le projet sous Windows

Ouvrez PowerShell dans le dossier du projet, puis exécutez :

```powershell
python --version
python -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip setuptools wheel
python -m pip install -r requirements.txt
```

Pour Streamlit :

```powershell
.\.venv\Scripts\Activate.ps1
python -m streamlit run app.py
```

URL : <http://localhost:8501>

Dans un autre terminal, pour FastAPI :

```powershell
.\.venv\Scripts\Activate.ps1
python -m uvicorn api:app --reload
```

API : <http://127.0.0.1:8000> — Swagger : <http://127.0.0.1:8000/docs>

Test PowerShell avec l'image fournie :

```powershell
curl.exe -F "image=@test_feu.jpg;type=image/jpeg" http://127.0.0.1:8000/predict
```

Tableau HTML local, avec FastAPI déjà lancé :

```powershell
python -m http.server 8080
```

Ouvrez <http://127.0.0.1:8080/index.html>. Arrêtez un serveur avec `Ctrl+C`. Si l'activation PowerShell est bloquée par la stratégie locale, utilisez directement `.\.venv\Scripts\python.exe -m ...`.
