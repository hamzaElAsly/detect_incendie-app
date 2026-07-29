# Matrice des permissions — Phase 3

Auditée le 29 juillet 2026 à partir des 115 routes Laravel, du seeder RBAC, des Policies, Form Requests et contrôleurs.

Abréviations : SA `super_admin`, PA `platform_admin`, FO `factory_owner`, FM `factory_manager`, SO `safety_officer`, SG `security_agent`, MA `maintenance_agent`, V `viewer`. SA bénéficie du `Gate::before`. Les autres rôles sont déterminés par le seeder et non par le libellé affiché.

| Module | Action / route | Rôles autorisés | Permission | Protection serveur | Tenant | Test |
|---|---|---|---|---|---|---|
| Dashboard | GET `dashboard` | SA, PA, FO, FM, SO, SG, MA, V | `factories.view` | `auth`, `verified`, `factory.web`, contrôleur | FactoryContext | existant |
| Factories | index/show | SA, PA; accès ressource selon Policy | `factories.view` | FactoryPolicy | explicite | existant + XSS |
| Factories | create/update/toggle/delete | SA/PA selon matrice | `factories.create/update/delete` | FactoryRequest + Policy | ressource vérifiée | existant |
| Members | index/create | rôles tenant selon seeder | `users.view/invite` | middleware factory + contrôleur | FactoryContext | existant |
| Members | store/update/delete | idem, owner réservé plateforme | `users.invite/update/remove` | MemberRequest + service + contrôle owner | membership actif | IDOR + escalade |
| Users | index/show/edit/update/delete | SA, PA selon permission | `users.view/update/remove` | UserWebPolicy + UserRequest | global | existant |
| Users | create/store | SA, PA; PA ne peut créer SA | contrôle de rôle | StoreGlobalUserRequest + service | global uniquement | existant |
| Roles/Permissions | list/show | SA, PA | `roles.view` | RoleWebPolicy | global | existant |
| Roles/Permissions | edit/update | SA, PA hors rôles plateforme immuables | `roles.manage` | Policy + garde système + Request | global | existant |
| Zones | CRUD | rôles selon seeder | `zones.*` | ZoneRequest + contrôleur | FactoryContext + ownership avant validation | IDOR |
| Cameras | CRUD | rôles selon seeder | `cameras.*` | CameraRequest + contrôleur | FactoryContext + zone tenant | IDOR |
| Camera Health | index/show | tous rôles tenant | `cameras.view` | contrôleur | FactoryContext | IDOR existant |
| Fire Events | index/show | tous rôles tenant | `events.view` | contrôleur | FactoryContext | existant |
| Fire Events | transitions | SO/SG et rôles supérieurs selon seeder | permission de transition | EventActionRequest + service transactionnel | event tenant | existant |
| Fire Events | GET `events/{event}/media/{kind}` | rôles autorisés en lecture | `events.view` | contrôleur média + kind allowlist | event tenant, disque privé | upload sécurité |
| Alerts | index/show | rôles selon seeder | `alerts.view` | contrôleur | FactoryContext | existant |
| Alerts | acknowledge | rôles selon seeder | `alerts.manage` | AlertAcknowledgeRequest + contrôleur | alert tenant | existant |
| Alert Recipients | CRUD | FO et rôles autorisés | `recipients.view/manage` | RecipientRequest + contrôleur + Policy | ownership avant validation | IDOR |
| API Clients | list/show/create/status/rotate/revoke | SA, PA | `api_clients.view/manage` | ApiClientRequest + contrôleur + Policy | FactoryContext | IDOR + API |
| Plans | CRUD/toggle | SA, PA | `plans.view/manage` | contrôleur + PlanRequest | global | existant |
| Subscriptions | CRUD | SA, PA | `subscriptions.view/manage` | contrôleur + SubscriptionRequest | global administré | existant |
| Profile | GET/PUT/password | utilisateur authentifié actif | soi-même | auth + ProfileRequest/PasswordRequest | soi-même | sécurité |
| Audit Logs | index | SA/PA autorisés | `audit_logs.view` | contrôleur | FactoryContext | isolation index |
| Settings | read/update | rôles autorisés | `settings.view/update` | WebSettingsRequest + contrôleur | FactoryContext | existant |
| Settings API | GET/PATCH `api/factories/{factory}/settings` | rôles autorisés | `settings.view/update` | auth API + Request/contrôleur | factory route vérifiée | sécurité |
| Reports | index/export | rôles autorisés | `reports.view` | contrôleur + limite export | requête factory-scoped | existant |
| Edge Heartbeat | POST `api/edge/cameras/{camera}/heartbeat` | API Client | `camera:heartbeat` | auth Edge + scope + throttle | factory du client = caméra | API sécurité |

## Carte tenant

Source de vérité web : session `factory_id`, résolue uniquement parmi `User::accessibleFactories()`, puis stockée dans `FactoryContext`. Source Edge : factory du `ApiClient` authentifié. Le payload n'est jamais source de vérité.

| Ressource | Colonne/liaison | Construction/lecture sûre |
|---|---|---|
| Members | `factory_user.factory_id` et `role_user.factory_id` | relation `Factory::users()` |
| Zones | `zones.factory_id` | `Zone::forFactory()` / `Factory::zones()` |
| Cameras | `cameras.factory_id` | `Camera::forFactory()` / `Factory::cameras()` |
| Camera Health | `camera_health_logs.factory_id` | caméra tenant + `forFactory()` |
| Events | `fire_events.factory_id` | `FireEvent::forFactory()` |
| Alerts | `alerts.factory_id` | `Alert::forFactory()` |
| Recipients | `alert_recipients.factory_id` | `Factory::alertRecipients()` |
| API Clients | `api_clients.factory_id` | `Factory::apiClients()` |
| Settings | `factory_settings.factory_id` | service recevant la Factory résolue |
| Reports | filtre obligatoire `fire_events.factory_id` | FactoryContext |
| Audit logs | `audit_logs.factory_id` | `AuditLog::forFactory()` |
| Media incidents | chemin factory/event + event contrôlé | contexte API utilisateur |
| Edge heartbeat | client et caméra | égalité stricte des deux factories |
