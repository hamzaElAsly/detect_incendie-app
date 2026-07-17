# Sécurité

- Contrôle tenant obligatoire et requêtes métier filtrées par `factory_id`.
- Mass assignment interdit pour `factory_id`; l'association est imposée par les services.
- Flux et credentials caméra chiffrés; mots de passe, tokens et secrets exclus des réponses.
- Secrets clients API stockés sous SHA-256; authentification agent HMAC/anti-rejeu prévue.
- Uploads limités à des MIME et tailles explicites, noms ULID et disque Laravel configurable.
- Audit expurge `password`, credentials, URL de flux, tokens et secrets.
- Rate limits authentification/API déjà actifs.

Sanctum et le générateur Swagger ne peuvent pas être installés tant que Packagist est inaccessible.
