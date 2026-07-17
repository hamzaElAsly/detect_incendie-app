# Schéma de données

Le schéma métier est défini par `2026_07_17_000002_create_fire_detection_domain.php`: RBAC, zones, caméras, credentials, santé, événements et détections, alertes, destinataires, notifications et journaux, paramètres, clients machine, audit, plans et abonnements.

Les clés tenant sont indexées avec les filtres fréquents. Les références zone/caméra et les paramètres sont uniques dans une usine. `event_uuid` est globalement unique. Les événements et ressources opérationnelles utilisent des ULID.
