# Edge Agent Laravel

Agent de capture et d'inférence conçu pour envoyer uniquement des détections
confirmées à l'API Laravel. Il ne déclenche aucun équipement industriel.

Le modèle entraîné n'est pas inclus. Tant qu'un `best.pt` évalué n'existe pas,
l'agent doit rester arrêté ou fonctionner en `dry_run`.

```powershell
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
copy .env.example .env
python -m src.main --dry-run
```

`EDGE_FACTORY_ID` est informatif uniquement : Laravel déduit toujours le tenant
du token API Client et vérifie la caméra. Le token ne doit jamais être journalisé.
