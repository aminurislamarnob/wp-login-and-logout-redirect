# Audit-Log Notifier — Implementation Plan

**Status:** Spec / planning (no code yet). Depends on Phase 1
([`audit-logs-plan.md`](audit-logs-plan.md)), which is merged. Hangs entirely off
the existing `wplalr_log_recorded` action that `includes/Logs/Logger.php` already
fires (`$row_id, $data`) and the cron pattern already in
`includes/Logs/Installer.php` — **no changes to the write path.**

This is the remaining piece of the audit-logs plan's §4.3a (Notifier) and §6
(digest cron), split out as a standalone follow-up.

---

## 1. Scope & decisions

Two independent, both-Free features sharing one class:

1. **Per-event alerts** — email an admin when a user whose role is in
   `wplalr_logs_notify_roles` logs in successfully. Off by default (empty roles).
2. **Digest** — a scheduled roll-up email (daily / weekly / monthly) of login
   activity. Off by default (`''`).

**Decisions (recommended defaults):**

- **Trigger event for alerts:** successful `login` only (not logout / failed /
  forced_logout in v1). Keeps it a "someone privileged just signed in" alert.
- **Send alerts async** — `wp_schedule_single_event( time(),
  'wplalr_send_login_alert', [ $row_id ] )` rather than calling `wp_mail()`
  inside the `wp_login` → `wplalr_log_recorded` path. Logging in should not block
  on SMTP. *(This is the one deviation from the original plan's "send inline";
  cheap, and avoids login latency.)*
- **Recipient:** `wplalr_logs_notification_email`, default resolved from
  `{admin_email}`.
- **Gating:** the Notifier only hooks anything when logging is enabled
  (`wplalr_enable_logs === 'yes'`) — same guard as `Logger`. If logging is off
  there are no rows and nothing to notify on.
- **`monthly` is not a core cron schedule** (core has
  hourly/twicedaily/daily/weekly). Register it via `cron_schedules`.

---

## 2. New options (folded into `/settings`, like the existing log options)

| Option | Default | Purpose |
|---|---|---|
| `wplalr_logs_notification_email` | `get_option('admin_email')` | Where alerts + digests go |
| `wplalr_logs_notify_roles` | `[]` | Roles whose login triggers an alert (`[]` = off) |
| `wplalr_logs_digest` | `''` | `''` \| `daily` \| `weekly` \| `monthly` digest cadence |

---

## 3. PHP — `includes/Logs/Notifier.php` (new)

Wired into the container in `WpLoginLogoutRedirect::init_classes()` after
`logger`. Constructor returns early unless `wplalr_enable_logs === 'yes'`.

**Per-event alerts:**

- `add_action( 'wplalr_log_recorded', 'maybe_queue_login_alert', 10, 2 )` — when
  `event === 'login'` and `wplalr_logs_notify_roles` is non-empty, intersect the
  user's roles (looked up from `$data['user_id']` — `$data` carries no roles, so
  `get_userdata()`) with the configured roles; if matched,
  `wp_schedule_single_event` the async sender with `$row_id`.
- `add_action( 'wplalr_send_login_alert', 'send_login_alert', 10, 1 )` — re-reads
  the row via `LogRepository::get( $id )` (see §6), builds + sends the email.

**Digest:**

- `add_action( 'wplalr_logs_digest_send', 'send_digest' )` — builds a roll-up
  from `LogRepository::stats()` + `query()` over the cadence window and emails
  it. No-op if `wplalr_logs_digest === ''`.

**Email building** (both `wp_mail`, HTML via a small template method):

- Subject/body filterable: `wplalr_log_notification_email` (alert) and
  `wplalr_log_digest_email` (digest) — `apply_filters` on
  `[ 'to', 'subject', 'body', 'headers' ]` so Pro/users can re-template.

---

## 4. Cron — extend `includes/Logs/Installer.php`

The cleanup cron pattern is already there; mirror it for the digest.

- **Register `monthly`** via `add_filter( 'cron_schedules', … )` (≈30 days).
- **Digest event** `wplalr_logs_digest_send`. It is **rescheduled whenever
  `wplalr_logs_digest` changes**, not on a fixed cadence:
  - `add_action( 'update_option_wplalr_logs_digest', 'reschedule_digest', 10, 2 )`
    (+ `add_option_wplalr_logs_digest`) → clear the existing event, and if the new
    value is non-empty, `wp_schedule_event( next_run, $cadence,
    'wplalr_logs_digest_send' )`.
  - `unschedule_cleanup()` grows into `unschedule_all()` (or add
    `unschedule_digest()`), called from `WpLoginLogoutRedirect::deactivate()`.
- The per-event alert uses single-events, so it needs no recurring registration.

---

## 5. REST + settings — `includes/REST/SettingsController.php`

- `get_settings()` — add the three options (resolve email default to
  `admin_email`; cast roles to array).
- `update_settings()` — sanitize: `sanitize_email` (fallback to admin_email if
  invalid/empty), roles `array_values( array_intersect( sanitize_key'd, valid
  roles ) )`, digest `in_array( $v, [ '', 'daily', 'weekly', 'monthly' ], true )`.
- Add the three fields to `get_item_schema()`.

---

## 6. `includes/Logs/LogRepository.php` — one addition

- `get( int $id ): ?array` — single-row fetch (the async alert sender needs to
  re-read the row by id; `query()` is list-only). Prepared statement, returns the
  `prepare_item()` shape or null.

---

## 7. React — extend the **Others** tab (`src/components/OthersSettings.js`)

Add a second card, **"Notifications"**, below the Audit Logging card (same
`SettingsContext` save path, one round-trip):

- **Notify on login (roles)** — multi-select from `window.wplalrAdmin.roles`
  (`FormTokenField` or a checklist) → `wplalr_logs_notify_roles`.
- **Notification email** — `TextControl type="email"` →
  `wplalr_logs_notification_email`.
- **Digest summary** — `SelectControl` (Off / Daily / Weekly / Monthly) →
  `wplalr_logs_digest`.

No new view, hook, or bundle — purely additive to the existing tab. Help text
notes both require logging enabled.

---

## 8. Testing / acceptance

- `composer phpcs` + `php -l`; `npm run build` + `npm run lint:js` clean.
- Configure a notify role → log in as that role → assert one queued alert email
  (capture with a `wp_mail` test interceptor or MailHog on the Herd site).
- Non-matching role → no email. Empty roles → no email.
- Set digest `daily` → assert `wp_next_scheduled('wplalr_logs_digest_send')` is
  set; change to `''` → assert cleared; change cadence → assert rescheduled.
- `monthly` appears in `wp_get_schedules()`.
- Logging off → Notifier hooks nothing.
- Manually run `do_action('wplalr_logs_digest_send')` → digest email with correct
  counts from `stats()`.

---

## 9. File checklist

**New PHP**
- `includes/Logs/Notifier.php`

**Changed PHP**
- `includes/Logs/Installer.php` — `monthly` schedule, digest (re)scheduling on
  option change, unschedule on deactivate.
- `includes/Logs/LogRepository.php` — `get( $id )`.
- `includes/REST/SettingsController.php` — three options in get/update/schema.
- `includes/WpLoginLogoutRedirect.php` — container: `new Logs\Notifier(...)`.

**Changed React**
- `src/components/OthersSettings.js` — Notifications card.

**Docs**
- `readme.txt` — feature + privacy note that emails contain login metadata.
- `docs/extensibility.md` — `wplalr_log_notification_email`,
  `wplalr_log_digest_email`.

---

## 10. Build order

1. Options + `SettingsController` fold + `LogRepository::get()` — no behavior yet.
2. `Notifier` per-event alerts (async single-event) + container wiring.
3. Digest: `monthly` schedule + option-change (re)scheduling + `send_digest`.
4. Others-tab Notifications card.
5. Email templates + filters + docs.

---

## 11. Open questions (recommended defaults)

- Async vs. inline alert send? → **Async single-event** (avoid login latency).
- Alert on events beyond login (failed / forced_logout)? → **Login only in v1**;
  widen later via the same role gate.
- Throttle alerts (many logins of a watched role)? → **No throttle v1**;
  document, revisit if noisy.
- HTML vs. plain-text email? → **HTML with a plain-text fallback header**,
  filterable.

---

Scope: ~1 new class + 1 new React card + small edits to 4 existing files; no
migrations, no new endpoints, no new bundle.
