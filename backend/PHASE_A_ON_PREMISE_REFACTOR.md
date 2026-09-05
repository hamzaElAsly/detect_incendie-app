# Phase A On-Premise Refactor

Date: 2026-09-04

## Previous Architecture

The application previously carried SaaS assumptions:

- Multiple customer factories could exist in one application instance.
- Users could be attached to factory memberships.
- Plans, subscriptions, billing, trial status, and quotas existed in the domain schema.
- Some API routes accepted a factory identifier as part of the URL.
- Roles included SaaS/platform names such as `super_admin`, `platform_admin`, and `factory_owner`.

## Target Architecture

The application now targets one on-premise installation per physical factory/site:

```text
IP Cameras / RTSP
  -> Python Edge Agent
  -> YOLO fire/smoke detection
  -> Laravel API
  -> MySQL
  -> Local Fire Command Center Dashboard
```

The Laravel installation resolves one local factory profile automatically. Users do not select or switch tenants in normal operation.

## Removed From Application Flows

- SaaS plan routes, controllers, views, navigation, permissions, and seeded access.
- SaaS subscription routes, controllers, views, navigation, permissions, and seeded access.
- Billing and tenant-management permissions.
- Factory collection management in the web UI.
- Factory create/delete permissions from the active permission seed.
- Tenant/factory switching from normal web and API context resolution.

## Retained Modules

- Authentication and email verification.
- Users and local factory members.
- Roles and permissions.
- Cameras and camera health.
- Fire/smoke detection history.
- Alerts, alert recipients, and notifications.
- Reports and CSV export protections.
- Audit logs with old/new values.
- API clients, secret rotation, revocation, scopes, and rate limiting.
- Edge heartbeat and detection endpoints.
- Incident snapshot/media upload and private download.
- Factory settings and system settings.

## Factory Model Decision

The `Factory` model and `factory_id` columns are intentionally retained.

Reasoning:

- Core camera, alert, detection, audit, settings, media, and API-client records already depend on `factory_id`.
- Retaining the key preserves historical data and authorization boundaries.
- The app resolves `Factory::local()` automatically and treats that record as the local site profile.
- Requests cannot switch to a foreign factory. Legacy factory-ID API routes are compatibility wrappers and are rejected unless the ID matches the local installation.

## Database Decisions

- A forward-only migration, `2026_09_02_000001_refactor_to_on_premise_installation.php`, adds local site profile fields to `factories`.
- The same migration safely maps legacy role names and removes stale SaaS permissions.
- Historical `plans` and `subscriptions` tables are not dropped in Phase A. They are unused by application flows and intentionally retained to avoid unnecessary destructive migration risk.
- Rollback for the additive profile columns is reasonable; destructive role/permission cleanup is not treated as a production rollback strategy.
- Local `php artisan migrate:status` currently shows the Phase A migration as pending on the configured development database. The test database applies the migration successfully through `php artisan test`.

## Role Mapping

- `super_admin` -> `system_admin`
- `platform_admin` -> `system_admin`
- `factory_owner` -> `factory_admin`
- `factory_manager` retained
- `safety_officer` retained
- `security_agent` retained
- `maintenance_agent` retained
- `viewer` retained

The active role set is:

- `system_admin`
- `factory_admin`
- `factory_manager`
- `safety_officer`
- `security_agent`
- `maintenance_agent`
- `viewer`

## Permission Changes

Removed or no longer seeded:

- `factories.create`
- `factories.delete`
- `plans.*`
- `subscriptions.*`
- `billing.*`
- `tenants.*`

Retained:

- users and roles
- zones
- cameras and camera credentials
- events/detections
- alerts and notifications
- recipients
- API clients
- settings
- reports
- audit logs

## Navigation And Dashboard

The sidebar now follows the on-premise product structure:

- Command Center: Dashboard, Live Monitoring, Alerts, Detection History
- Infrastructure: Zones, Cameras, Camera Health, Alert Recipients
- Administration: Members, Users, Roles and Permissions, API Clients, Audit Logs
- Operations: Reports, Notifications
- System: Factory Settings, System Settings, Profile

The dashboard uses local operational metrics only:

- total, online, and offline cameras
- active incidents
- camera availability
- average response time
- factory zones
- seven-day event statistics
- recent events
- equipment health and recent activity

No SaaS metrics such as MRR, subscriptions, plans, platform factory totals, or tenant growth are present.

## Edge Agent Compatibility

Edge-related functionality remains in place:

- API Client authentication is retained.
- Client scopes, expiration, revocation, and rotation remain tested.
- Heartbeat endpoint remains available at `/api/edge/cameras/{camera}/heartbeat`.
- Detection endpoint remains available at `/api/edge/cameras/{camera}/detections`.
- Detection idempotency, snapshot upload, validation, and audit logging remain tested.
- Installation-scoped API routes are available under `/api/installation/...`.
- Legacy `/api/factories/{factory}/...` routes remain as compatibility aliases and reject non-local factory IDs.

No real RTSP camera validation was performed in Phase A.

## Offline-First Considerations

- Laravel Blade assets are built locally with Vite.
- No external CDN, remote font, analytics, Stripe, licensing, or cloud-auth dependency was found in Laravel runtime views/assets.
- The standalone Python HTML prototype no longer imports Google Fonts or falls back to a hosted Render API; it defaults to `http://127.0.0.1:8000`.
- Remaining external URLs are documentation/schema/comment/default configuration references, not core runtime dependencies.
- Optional notification channels may still require external connectivity depending on deployment configuration, but the command center, local database, API clients, camera health, alerts, detections, and dashboard do not depend on Internet access.

## Validation Performed

- `php artisan test`: 55 tests passed, 379 assertions.
- `npm.cmd run build`: Vite production build passed, 55 modules transformed.
- `php artisan route:list`: 106 routes loaded.
- `php artisan route:list --path=plans`: no matching routes.
- `php artisan route:list --path=subscriptions`: no matching routes.
- `php artisan route:list --path=billing`: no matching routes.
- `php artisan route:list --path=tenant`: no matching routes.
- `php artisan migrate:status`: Phase A migration pending on the configured local database.
- `composer validate --no-check-publish`: `composer.json` valid; Composer emitted git dubious-ownership warnings in the sandbox.
- `vendor/bin/pint --dirty`: passed.

## Known Remaining Work

- Apply the pending Phase A migration on the target local database after backup.
- Perform manual browser smoke testing of the updated sidebar and dashboard.
- Perform deployment-environment offline testing with Internet disconnected.
- Validate real RTSP camera streams and real Edge Agent hardware in a later phase.
- Decide in a later cleanup phase whether to archive or drop legacy `plans` and `subscriptions` tables after deployment data is audited.

## Phase A Status

Phase A is complete for the Laravel application structure, automated tests, route validation, local asset build, and documentation.

It is not a claim of production readiness, real factory validation, real RTSP validation, installer readiness, or deployment validation.
