# Intégration agent Python

L'agent doit utiliser un `client_id`, un timestamp UTC, un nonce et une signature HMAC du corps exact. Le serveur doit refuser les timestamps hors fenêtre, les nonces réutilisés, les IP non autorisées et les signatures non constantes. `event_uuid` rend la création idempotente. La caméra est résolue par `camera_reference` dans l'usine du client.

Le flux cible est: validation client et caméra, transaction événement + détections + alerte, puis job de notifications après commit.
