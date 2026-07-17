# Architecture backend

Laravel 12, API REST, base MySQL partagée et isolation par `factory_id`. Les contrôleurs délèguent aux services de domaine sous `app/Domain`. Les ressources exposées utilisent des ULID; `event_uuid` assure l'idempotence machine. Les agrégations Dashboard s'exécutent en base et les événements récents chargent caméra et zone en eager loading.

Les secrets caméra sont chiffrés par les casts Laravel et masqués. Les logs d'audit appliquent une liste de champs sensibles systématiquement expurgés. La queue locale est `database`; Redis/Horizon reste une option Linux de production.
