# Lacadev Client Port Notes

Last reviewed: 2026-05-08

This project uses two themes:

- `lacadev-client`: parent theme containing shared customer-site logic.
- `lacadev-client-child`: child theme containing project-specific overrides and build assets.

The current port follows the lightweight customer-site mode from the internal `lacadev` upgrade notes. The full `Laca Projects` CRM hub was intentionally not copied into the customer theme.

## What Was Ported

### Laca Admin Dock

Added:

- `app/src/Settings/LacaAdmin/LacaAdminMenuOrganizer.php`

Registered from:

- `app/hooks.php`

Purpose:

- Hide the default flat WordPress submenu under `Laca Admin`.
- Render a clean grouped internal dock.
- Group existing customer-site admin pages into:
  - Tổng quan / Cấu hình chung
  - Hiệu năng & bảo trì
  - Bảo mật & đăng nhập
  - Nội dung & cấu trúc
  - Kết nối & vận hành
  - Marketing & AI

### Tracker Lifecycle Fix

Updated:

- `app/hooks.php`

Before:

- `LacaDevTrackerClient` was instantiated only inside `is_admin()`.

After:

- `LacaDevTrackerClient::register()` runs on `init` for all WordPress requests.
- This keeps WP-Cron scans and REST routes available outside wp-admin.
- Sending logs remains a no-op until tracker endpoint and secret key are configured.
- Admin-only update widgets still stay inside the admin-only block.

### Client Request Endpoint

Updated:

- `app/src/Settings/LacaDevTrackerClient.php`

Added endpoint:

- `POST /wp-json/laca/v1/client/request`

Behavior:

- Public endpoint for customer-site support/request forms.
- Requires tracker endpoint and secret key to be configured.
- Accepts `request_type`, `message`, `contact_name`, `contact_email`.
- Allowed request types: `request`, `bug`, `content`, `maintenance`, `billing`.
- Rate-limits by IP for 5 minutes.
- Sends a tracker log with `type = client_request` to the central Lacadev project system.

This endpoint does not require a local `project` CPT on the customer website.

### Tracker Payload Filter

Updated:

- `app/src/Settings/LacaDevTrackerClient.php`

The outgoing tracker payload now passes through:

- `HookNames::TRACKER_PAYLOAD`
- Filter name: `lacadev_tracker_payload`

This allows child themes or client-specific code to add safe metadata without editing the parent tracker class.

### Tracker Reliability + Client Operations

Added:

- `app/src/Databases/TrackerEventTable.php`
- `app/src/Settings/LacaTools/Management/ClientOperationsPage.php`

Updated:

- `app/src/Settings/LacaDevTrackerClient.php`
- `theme/functions.php`
- `app/src/Settings/LacaTools/ManagementExperience.php`
- `app/src/Settings/LacaTools/Management/DashboardWidgets.php`
- `app/src/Settings/LacaAdmin/LacaAdminMenuOrganizer.php`

Behavior:

- Tracker logs now pass through a local outbox table before delivery.
- Failed delivery is retried by WP-Cron.
- Tracker health stores last success, last failure, HTTP code and error.
- `Laca Admin > Client Operations` shows tracker status, queue counts, support requests, block sync state and remote maintenance history.
- WordPress Dashboard shows a compact Client Operations widget.
- Remote update actions are stored locally in `_laca_remote_update_history`.
- Schema version bumped to `1.1.0` to install `wp_laca_tracker_events`.

Shortcode:

- `[laca_support_center]`

Support request behavior:

- Public support form posts to `/wp-json/laca/v1/client/request`.
- Generates a request ID.
- Accepts image attachments.
- Sends request metadata and attachment URLs to the central `lacadev` tracker endpoint.
- If central delivery fails but the local outbox is available, the request is stored locally and retried automatically.

### Tracker Admin Note

Updated:

- `app/src/Settings/AdminSettings.php`

The Tracker settings page now mentions the local request endpoint:

- `/wp-json/laca/v1/client/request`

## What Was Not Ported

These internal CRM pieces were intentionally not copied:

- `LacaProjectsHub.php`
- `project` CPT menu move under `laca-projects`
- CRM dashboard, finance, pipeline, notifications, reports and portal-link manager
- Project DB tables and local `ProjectLog` / `ProjectAlert` models
- Project detail metabox workspace
- Permission and role system

Reason:

- `lacadev-client` is for customer websites.
- The customer website should report to the central Lacadev CRM, not become a separate CRM itself.

## Central CRM Compatibility

The central `lacadev` tracker receiver was updated to understand logs with:

- `type = client_request`
- `type = heartbeat`

Central behavior:

- Stores the log as `ProjectLog` type `client_request`.
- Stores client request metadata and attachment URLs in `ProjectLog.meta`.
- Creates a `bug`/`warning` alert for bug reports.
- Creates an `other` alert for general support, content, maintenance or billing requests.
- Heartbeat updates `_tracker_last_seen_at`, `_tracker_last_seen_message` and `_tracker_last_seen_meta` without creating noisy project logs.
- Makes requests visible in the Laca Projects Notifications view.

## Phase 3 and 4 Operations Upgrade

Remote maintenance now includes:

- Preflight validation before plugin/theme/core updates.
- `dry_run` support on `/wp-json/laca/v1/remote-update` to inspect preflight and snapshot without changing files.
- Snapshot before/after each remote update.
- Temporary maintenance mode for core/theme updates by default, or plugin updates when `maintenance_mode` is sent.
- Failure/skipped history with rollback notes and preflight warnings/errors.

Block Sync now includes:

- Payload diagnostics before writing files.
- Required `block.json` validation.
- Warnings for missing editor/render files.
- Last synced version, synced by, file count, compatibility snapshot.
- Diagnostics returned by `/wp-json/lacadev/v1/sync-block/status`.

Client Operations now shows:

- Operations readiness.
- Preflight warning/error counts in remote maintenance history.
- Block sync diagnostics table.
- Weekly summary metadata includes remote status counts and block diagnostics.

## Child Theme Notes

No core logic was added to `lacadev-client-child`.

Use the child theme for:

- Project-specific templates.
- Project-specific SCSS/JS.
- Optional form UI that posts to `/wp-json/laca/v1/client/request`.
- Optional filters on `lacadev_tracker_payload`.

Avoid putting shared tracker/admin logic in the child theme unless it is truly project-specific.

## Validation Checklist

Run from the parent theme:

```bash
php -l app/hooks.php
php -l app/src/Settings/LacaAdmin/LacaAdminMenuOrganizer.php
php -l app/src/Settings/LacaDevTrackerClient.php
php -l app/src/Settings/AdminSettings.php
```

Run from the child theme if assets were changed:

```bash
yarn lint:styles
yarn lint:scripts
yarn build:theme
```

Runtime checks:

- `Laca Admin` should show the grouped dock instead of a flat submenu.
- `Laca Admin > Tracker` should show configured/unconfigured status.
- `Laca Admin > Client Operations` should show tracker health, queue and recent support requests.
- `Laca Admin > Client Operations` should provide manual actions for heartbeat, retry queue and weekly summary.
- `[laca_maintenance_timeline]` should render a public-safe timeline of remote updates, block sync activity, tracker maintenance events and support request receipts.
- Block Sync receiver now emits a `block_sync` tracker event after receiving/updating a block, so central `lacadev` can record it as project deployment activity.
- A weekly `maintenance_summary` event should be queued/sent to the central `lacadev` tracker endpoint.
- `POST /wp-json/laca/v1/remote-update` with `dry_run: true` should return preflight and snapshot without running the updater.
- `POST /wp-json/laca/v1/client/request` should return `503` when tracker is not configured.
- When tracker is configured, the same endpoint should send a `client_request` log to the central Lacadev project.
- Existing remote update endpoint `/wp-json/laca/v1/remote-update` should still authenticate by tracker secret key.
