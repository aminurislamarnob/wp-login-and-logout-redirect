# Audit Logs — Implementation Plan

Status: **Spec / planning** (no code yet). Next phase after the rule engine
(Phases 1–4 of [`free-pro-roadmap.md`](free-pro-roadmap.md)) lands.

Goal: record an auditable trail of login / logout / failed-login events into a
**custom database table**, and surface it as a paginated, filterable, searchable
**Logs** tab in the existing React admin app — modeled on FluentAuth
(`fluent-security`)'s `fls_auth_logs` table and `LogsController`, but adapted to
this plugin's conventions (PSR-4 `includes/`, `WP_REST_Controller`, `@wordpress`
React app, PHPCS/WPCS).

This extends our existing differentiator: we already track **last login** as user
meta (`UserLoginTime`). Audit logs generalize that into a full, queryable event
history — something LoginWP and Sky do not ship as core, and which FluentAuth only
offers inside a heavier security suite.

---

## 1. Reference: how FluentAuth does it

Verified against actual source, not marketing:

- **Table** `{prefix}fls_auth_logs` created in `Activator::migrateLogsTable()` via
  `dbDelta()`, guarded by a `SHOW TABLES LIKE` check. Columns: `id`, `username`,
  `user_id`, `count`, `agent`, `browser`, `device_os`, `ip`, `status`,
  `error_code`, `media`, `description`, `created_at`, `updated_at`, with indexes
  on `created_at`, `ip`, `status`, `media`, `user_id`, `username`.
- **Writes** happen in `LoginSecurityHandler`: `logAuthSuccess()` on
  `wp_login`, `logFailedAuth()` on `wp_login_failed`, plus a `logBlockedAuth()`
  for rate-limited attempts (security feature — **out of scope** for us). Each
  write is gated behind a setting (`enable_auth_logs`). Repeated blocked attempts
  from one IP increment `count` rather than inserting a new row.
- **Read API** `LogsController`: `getLogs` (paginate + `status` filter + `LIKE`
  search on `username`/`media`, returns `human_time_diff`), `deleteLog`,
  `deleteAllLog` (TRUNCATE), `quickStats` (grouped counts by status over a date
  range).
- **Retention** `Helper::cleanUpLogs()` deletes rows older than
  `auto_delete_logs_day`, run from a scheduled `daily` cron event registered in
  the activator.
- FluentAuth uses its own query-builder (`flsDb()`); **we will use `$wpdb`
  directly** with prepared statements to avoid a dependency.

We deliberately drop FluentAuth's security-suite pieces (blocked-login counting,
login-attempt limiting, magic-login hash table). We keep the **audit trail**:
login success, logout, and failed login.

---

## 2. Product decisions to confirm

These shape the build; defaults are recommended in §11.

1. **Logout logging** — FluentAuth logs login success/fail but not logout. Since
   this plugin is fundamentally about *both* login and logout, we should log
   logout too (`wp_logout`). Recommended: **yes, log logout**.
2. **Free vs Pro split** — basic logging + viewer (this plan) is **Free** and a
   natural extension of the Last-Login differentiator. CSV export and extended
   analytics are already marked **Pro** in the roadmap (§3 of free-pro-roadmap).
3. **Default on/off** — installs that never open the page shouldn't silently grow
   a table. Recommended: logging **off by default**, enabled via a toggle on the
   Logs tab; show an empty state explaining how to enable.
4. **GDPR/PII** — IP address + user agent are personal data. Provide retention
   (auto-delete after N days) and a "delete all" action; document it. Recommended
   retention default: **30 days**.

---

## 3. Data model

New custom table `{prefix}wplalr_auth_logs`. No migration of existing data;
`wplalr_last_login` user meta stays as-is (the Last Login column keeps working,
optionally backed by the new table later — not in this phase).

```sql
CREATE TABLE {prefix}wplalr_auth_logs (
  id          BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,
  username    VARCHAR(192) NOT NULL,
  event       VARCHAR(20)  NOT NULL,          -- 'login' | 'logout' | 'failed'
  status      VARCHAR(20)  NOT NULL DEFAULT 'success', -- 'success' | 'failed'
  redirect_url VARCHAR(255) NULL,             -- the resolved redirect target
  rule_id     VARCHAR(64)  NULL,              -- which RuleEngine rule matched (or null)
  ip          VARCHAR(50)  NULL,
  agent       VARCHAR(255) NULL,
  browser     VARCHAR(50)  NULL,
  device_os   VARCHAR(50)  NULL,
  error_code  VARCHAR(50)  NULL DEFAULT '',
  description TINYTEXT     NULL,
  created_at  TIMESTAMP    NULL,
  KEY created_at (created_at),
  KEY user_id   (user_id),
  KEY event     (event(20)),
  KEY ip        (ip(50))
) {charset_collate};
```

Notes:
- `redirect_url` + `rule_id` are **our additions** over FluentAuth — they tie each
  event back to the redirect that fired, which is this plugin's domain. `rule_id`
  references the stable id in `wplalr_redirect_rules`.
- Drop FluentAuth's `count`/`media`/`updated_at` (those served blocked-attempt
  coalescing and magic-login, which we don't have). One row per event.

### Last Login integration (Free)

The plugin already tracks last login as `wplalr_last_login` user meta and renders
a sortable **Last Login** column via `UserLoginTime`. The audit log generalizes
that into a full event history. Decisions to keep them coherent — **all Free**:

- **Keep `wplalr_last_login` user meta as the source of truth for the column.**
  It's O(1) to read per user-row and works even when logging is toggled off. Do
  **not** make the users-list column query the logs table (would be a slow join
  across the user list). `UserLoginTime` stays as-is.
- **`Logger` updates both** on `wp_login`: it writes the audit row *and* refreshes
  `wplalr_last_login` (or `UserLoginTime` keeps owning the meta and `Logger` only
  writes the row — pick one writer; recommended: `UserLoginTime` keeps the meta,
  `Logger` adds the row, so the column never regresses if logging is off).
- **Enrich the column from the log when available:** when logging is on, the
  Last Login column tooltip/secondary line can show IP + browser from the latest
  `login` row for that user (cheap single-row lookup, cached per request). Pure
  enhancement; degrades to the plain timestamp when logging is off.
- **Per-user history:** the Logs tab's search already filters by username, giving
  a per-user login history for free — no extra UI needed in v1. (Pro adds last
  *logout* + login *count* analytics on top.)

New options:

| Option key | Tier | Purpose |
|---|---|---|
| `wplalr_enable_logs` | Free | `'yes'`/`'no'` — master switch for logging |
| `wplalr_logs_retention_days` | Free | int — auto-delete rows older than this (0 = keep forever) |
| `wplalr_db_version` | Free | schema version string, drives `dbDelta` upgrades |
| `wplalr_logs_notification_email` | Free | where event-notification emails are sent (default `{admin_email}`) |
| `wplalr_logs_notify_roles` | Free | email an admin when a user of one of these roles logs in (`[]` = off) |
| `wplalr_logs_digest` | Free | `''`/`'daily'`/`'weekly'`/`'monthly'` — scheduled login-activity digest email |

### Settings considered, mapped to FluentAuth

FluentAuth's log layer exposes more than retention. The full set and our decision:

| FluentAuth setting | Our decision |
|---|---|
| `enable_auth_logs` | **Free** — `wplalr_enable_logs` |
| `auto_delete_logs_day` | **Free** — `wplalr_logs_retention_days` (the field in the FluentAuth screenshot) |
| `notification_email` | **Free** — `wplalr_logs_notification_email` |
| `notification_user_roles` | **Free** — `wplalr_logs_notify_roles` (per-event email alerts) |
| `digest_summary` | **Free** — `wplalr_logs_digest` (scheduled roll-up email) |
| `notify_on_blocked` | **Dropped** — security-only; we have no login-blocking |

Per-event email alerts and the digest ship in Free as a differentiator over
LoginWP/Sky. Implementation: a `Notifier` listens on `wplalr_log_recorded`
(per-event, gated on `wplalr_logs_notify_roles`); the digest rides a scheduled
cron event (`wplalr_logs_digest` cadence) reusing the §6 cron infrastructure.

---

## 4. PHP architecture (aligned to `includes/`)

All classes in namespace `PluginizeLab\WpLoginLogoutRedirect`, PSR-4 from
`includes/`, registered in `WpLoginLogoutRedirect::init_classes()`'s `$container`.

### 4.1 `includes/Logs/Installer.php` (new)
- `maybe_install()` — runs `dbDelta()` for the table when `wplalr_db_version` is
  missing/stale (mirrors `Activator::migrateLogsTable`). Call from the existing
  `WpLoginLogoutRedirect::activate()` (currently empty) **and** from an
  `admin_init`/`plugins_loaded` version check so updates without reactivation
  still migrate.
- Registers/unregisters the daily cleanup cron event.

### 4.2 `includes/Logs/LogRepository.php` (new)
Thin `$wpdb` data layer — the only place that touches the table:
- `insert( array $data ): int`
- `query( array $args ): array` — paginated list: `page`, `per_page`, `event`,
  `status`, `search` (LIKE on `username`/`ip`), `orderby`, `order`. Returns
  `[ 'items' => [...], 'total' => int, 'pages' => int ]`. All inputs sanitized;
  `orderby`/`order` allowlisted; queries via `$wpdb->prepare`.
- `delete( int $id ): bool`
- `delete_all(): void` — `TRUNCATE`.
- `stats( string $range ): array` — grouped counts by `event`/`status` for the
  dashboard cards (mirrors `quickStats`).
- `purge_older_than( int $days ): int` — used by cron.

### 4.3 `includes/Logs/Logger.php` (new)
Hooks the WP auth lifecycle and writes through `LogRepository`. Gated on
`wplalr_enable_logs === 'yes'`:
- `wp_login` (prio 20, 2 args) → success row. `UserLoginTime` keeps owning the
  `wplalr_last_login` meta (so the Last Login column never regresses when logging
  is off); `Logger` only adds the audit row. Capture the resolved redirect: have
  `Redirection` stash the
  resolved URL + matched `rule_id` (e.g. via a transient-free request-scoped
  property or a `do_action( 'wplalr_after_resolve', ... )` payload that Logger
  listens to). The roadmap already plans `wplalr_after_resolve` — **reuse it**.
- `wp_login_failed` → failed row (status `failed`, `error_code` from the
  `WP_Error`/username).
- `wp_logout` → logout row.
- A small `UserAgent` parser (`includes/Logs/UserAgent.php`) for `browser` /
  `device_os` — a compact regex map; do **not** vendor FluentAuth's
  `BrowserDetection`. Keep it dependency-free and PHPCS-clean.

Each write fires `do_action( 'wplalr_log_recorded', $row_id, $data )` so the
notifier (and Pro geo-IP enrichment) can hook it.

### 4.3a `includes/Logs/Notifier.php` (new, Free)
Email layer driven by the logs:
- Listens on `wplalr_log_recorded`; when the event is a successful login and the
  user's role intersects `wplalr_logs_notify_roles`, sends an alert email to
  `wplalr_logs_notification_email` (`{admin_email}` token resolved). No-op when
  the roles list is empty.
- Digest: handler for the `wplalr_logs_digest` cron event (cadence from the
  option) builds a roll-up (counts + recent rows via `LogRepository::stats`/
  `query`) and emails it. Templated, `wp_mail`-based; filterable subject/body via
  `wplalr_log_notification_email` / `wplalr_log_digest_email`.

### 4.4 `includes/REST/LogsController.php` (new)
A second `WP_REST_Controller`, namespace `wplalr/v1`, base `logs`, cap
`manage_options`. Registered alongside `SettingsController` in
`WpLoginLogoutRedirect::register_rest_routes()`:
- `GET    wplalr/v1/logs` → list (query args above).
- `DELETE wplalr/v1/logs/(?P<id>\d+)` → delete one.
- `DELETE wplalr/v1/logs` → delete all.
- `GET    wplalr/v1/logs/stats` → dashboard cards.
- `GET/POST wplalr/v1/logs/settings` *(or fold into the existing settings
  endpoint)* → read/write `wplalr_enable_logs` + `wplalr_logs_retention_days`.
  Recommended: **fold the two log options into the existing
  `wplalr/v1/settings`** payload to keep one settings round-trip; keep the log
  *data* endpoints separate. Each item includes a computed `human_time_diff`.

Extension filters mirroring the roadmap: `wplalr_rest_log_item`,
`wplalr_rest_logs_query_args`.

### 4.5 Wiring
- `init_classes()`: add `logs_repository`, `logger`, `logs_installer` (or run the
  installer's version check directly). `Redirection` gets a way to expose the
  resolved redirect/rule to `Logger` (via the existing `wplalr_after_resolve`
  action).
- `WpLoginLogoutRedirect::activate()`: call `Installer::maybe_install()` +
  schedule cron.
- `deactivate()`: clear the cron event (keep the table + data).

---

## 5. React admin UI (`src/`)

**Delivery: a dedicated submenu page rendered as one view of the shared React
app** — see [`admin-subpages-plan.md`](admin-subpages-plan.md). (Supersedes the
earlier "Logs tab inside the existing SPA" approach.) An **Audit Logs** submenu
under Redirect Options (`admin.php?page=wplalr_audit_logs`), mount node
`#wplalr-audit-logs`. The single `admin/script` bundle is enqueued on this screen
and its mount-node dispatcher renders `<AuditLogsApp>` — no separate webpack
entry/bundle.

- **`<AuditLogsApp>`** (new view) — mounted on `#wplalr-audit-logs` by the
  `src/admin.js` dispatcher, wrapped in the shared `PageShell` (header +
  cross-page nav). Single view, no internal router.
- **`src/components/LogsViewer.js`** (new) — top section:
  - **Stat cards** (success / failed / logout counts) from `/logs/stats`.
  - **Enable logging** toggle + **retention** field.
  - **Notifications** sub-panel: notify-on-login role multi-select
    (`wplalr_logs_notify_roles`), notification email, and digest cadence
    `SelectControl` (off / daily / weekly / monthly). All Free.
  - **Toolbar**: search box, event/status filter `SelectControl`, "Delete all"
    (with a confirm `Modal`).
  - **Table**: paginated rows (time, user, event, status, IP, browser/OS,
    redirect URL, delete action). Use `@wordpress/components` (`Card`, `Spinner`,
    `Button`, `SelectControl`) to match existing styling; a lightweight table —
    no new table dep needed.
  - **Empty state** when logging is off or no rows yet.
- **`src/context/`** — either extend `SettingsContext` or add a small
  `useLogs()` hook doing `apiFetch` against `/wplalr/v1/logs` with pagination
  state. Recommended: a dedicated hook to keep `SettingsContext` focused.
- Reuse the `@wordpress/notices` snackbar pattern for delete confirmations.
- Pro slots: surface optional columns/actions via `wplalr_log_columns` /
  `wplalr_log_row_actions` `@wordpress/hooks` filters (CSV export button lands
  here in Pro).

---

## 6. Retention & cron

- Daily event `wplalr_logs_cleanup` (registered on activation, like FluentAuth's
  `fluent_auth_daily_tasks`). Handler calls
  `LogRepository::purge_older_than( wplalr_logs_retention_days )` when retention
  > 0.
- Digest event `wplalr_logs_digest_send` — (re)scheduled to match the
  `wplalr_logs_digest` cadence whenever the option changes; cleared when set to
  `''`. Handler → `Notifier` builds and sends the roll-up.
- Unschedule both on deactivation.

---

## 7. Security / privacy

- All reads/writes capability-gated (`manage_options`) and nonce-checked via the
  REST cookie auth the existing controller already uses.
- Every dynamic query through `$wpdb->prepare`; `orderby`/`order` allowlisted;
  `esc_url_raw` for `redirect_url`; `sanitize_text_field` for the rest.
- Document the table + IP/UA collection in `readme.txt` (privacy section) and
  provide the delete-all + retention controls for GDPR.
- Logging **off by default** so no PII is collected unless the admin opts in.

---

## 8. Testing / acceptance

- `composer phpcs` + `php -l` clean on all new PHP.
- `npm run build` + `npm run lint:js` clean.
- Manual (extend [`browser-test-cases.md`](browser-test-cases.md)):
  successful login writes a `login`/`success` row with the right redirect URL +
  matched rule id; bad password writes a `failed` row; logout writes a `logout`
  row; filters/search/pagination/delete/delete-all work; toggling logging off
  stops new rows; retention cron prunes old rows; table is created on
  activation and on version bump without reactivation.
- Multisite: install per-blog (loop `get_sites()` in the activator, like
  FluentAuth) — or document single-site only for v1.

---

## 9. File checklist

**New PHP**
- `includes/Logs/Installer.php`
- `includes/Logs/LogRepository.php`
- `includes/Logs/Logger.php`
- `includes/Logs/Notifier.php`
- `includes/Logs/UserAgent.php`
- `includes/REST/LogsController.php`

**Changed PHP**
- `includes/WpLoginLogoutRedirect.php` (container wiring, activate/deactivate,
  REST registration, version check)
- `includes/Redirection.php` (expose resolved URL + rule id via
  `wplalr_after_resolve`)

**New React**
- `src/components/AuditLogsApp.js` (new view, rendered by the `src/admin.js`
  dispatcher — no new webpack entry)
- `src/components/LogsViewer.js`
- `src/hooks/useLogs.js`

**Changed build / PHP (see [`admin-subpages-plan.md`](admin-subpages-plan.md))**
- `webpack.config.js` — none (single entry)
- `src/admin.js` (register the `#wplalr-audit-logs` → `<AuditLogsApp>` mount)
- `includes/Settings.php` (Audit Logs submenu + mount node)
- `includes/Assets.php` (enqueue `admin/script` on the Audit Logs screen)

**Docs / meta**
- `readme.txt` privacy + changelog
- `docs/extensibility.md` (new filters/actions)
- `docs/browser-test-cases.md` (Logs tab cases)

---

## 10. Build order (phased)

1. **DB + write path (no UI):** `Installer` + `LogRepository` + `Logger`;
   activation hook creates the table; logging gated behind `wplalr_enable_logs`.
   Acceptance: rows appear for login/logout/failed via direct DB inspection.
2. **Read REST API:** `LogsController` (list/stats/delete/delete-all) + fold the
   two options into `/settings`.
3. **React Logs tab:** `LogsViewer` (table, filters, search, pagination, stat
   cards, enable toggle, retention, notifications sub-panel, delete actions).
4. **Retention cron + `Notifier` (per-event alerts + digest cron) + privacy docs
   + extension filters.**
5. *(Pro, later)* CSV export, extended analytics (login counts, last logout),
   geo-IP enrichment — hooking the Phase-4 filters.

---

## 11. Open questions (recommended defaults)

- Log logout events? → **Yes** (core to this plugin's purpose).
- Logging default state? → **Off**, opt-in via the Logs tab.
- Retention default? → **30 days**, configurable (0 = forever).
- Fold log-settings into `/wplalr/v1/settings` or a separate endpoint? →
  **Fold the two options into `/settings`**, keep log *data* endpoints separate.
- Multisite per-blog install in v1, or single-site only? → **Single-site for v1**,
  multisite loop as a fast follow.
- Browser/OS parsing: ship a tiny regex parser vs. skip browser/OS columns for
  v1? → **Tiny parser** (small, dependency-free) — or defer the two columns if it
  risks PHPCS noise.
- Email notifications + digest summary (FluentAuth's `notification_*` /
  `digest_summary`): Free or Pro? → **Free (decided)** — shipped in Free as a
  differentiator over LoginWP/Sky via `includes/Logs/Notifier.php`. Pro keeps CSV
  export + extended analytics + geo-IP.
