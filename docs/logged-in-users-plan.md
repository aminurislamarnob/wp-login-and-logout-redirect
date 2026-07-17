# Logged-in Users & Force Logout — Implementation Plan

Status: **Spec / planning** (no code yet). Companion to
[`audit-logs-plan.md`](audit-logs-plan.md); reuses its `UserAgent` parser, REST
patterns, and (optionally) writes to its log table.

Goal: a **Sessions** tab listing users with active login sessions in a table, and
the ability to **force logout** — per session, per user, selected users (bulk),
or everyone. All **Free**.

This pairs naturally with our Last Login / audit-log differentiators: Last Login
says *when* someone last logged in; Sessions says *who is logged in right now* and
lets an admin end those sessions.

---

## 1. Data source — WordPress session tokens

WordPress already persists every active login session. We do **not** invent
session tracking — we read and act on core's store:

- Sessions live in the `session_tokens` user meta, managed by
  `WP_Session_Tokens` / `WP_User_Meta_Session_Tokens`. Each entry has
  `expiration`, `login` (login timestamp), `ip`, and `ua` (user agent).
- **List active sessions:** the set of users with a non-empty `session_tokens`
  meta. One efficient query against `usermeta`
  (`meta_key = 'session_tokens'`), then `WP_Session_Tokens::get_instance( $uid )
  ->get_all()` per user to expand individual sessions. Filter out expired tokens
  (expiration < now) — core lazily garbage-collects, so the raw meta can hold
  stale rows.
- **Force logout (the supported APIs):**
  - one session: `WP_Session_Tokens::get_instance( $uid )->destroy( $token )`
    (we key sessions by a hash/verifier, never expose the raw token).
  - all sessions for a user: `…->destroy_all()`.
  - everyone: `WP_Session_Tokens::destroy_all_for_all_users()` (static).

Because this is core's own store, it works with any auth plugin that uses
standard WP sessions (including our own redirects, WooCommerce, etc.).

---

## 2. Scope

**In scope (Free):**
- Sessions table: user, role(s), login time, expiration / "expires in", IP,
  browser/OS, session count, current-session flag.
- Force logout: single session, whole user, **bulk (selected users)**, and
  **everyone**.
- Search (username/email) + role filter + pagination.
- Safeguards around logging out the **current** admin.

**Out of scope (later / Pro candidates):**
- Live auto-refresh / real-time presence (v1 is on-demand refresh).
- Per-session geolocation, concurrent-session limits, "kick on Nth login".
- Scheduled/automatic idle logout.

---

## 3. PHP architecture (aligned to `includes/`)

Namespace `PluginizeLab\WpLoginLogoutRedirect`, PSR-4 from `includes/`, wired into
`WpLoginLogoutRedirect::init_classes()`'s `$container`.

### 3.1 `includes/Sessions/SessionRepository.php` (new)
The only place that reads/acts on session data:
- `query( array $args ): array` — paginated list of **users with active
  sessions**. Args: `page`, `per_page`, `search` (user_login/email/display_name),
  `role`, `orderby`, `order`. Strategy: query distinct `user_id`s from
  `usermeta` where `meta_key='session_tokens'` (with `LIMIT`/`OFFSET` for
  pagination and a `COUNT` for totals), then hydrate each via `get_userdata` +
  `WP_Session_Tokens::get_instance()->get_all()`. Returns
  `[ 'items' => [...], 'total' => int, 'pages' => int ]`, each item carrying the
  user summary + its (non-expired) sessions (each with a stable
  `token_id` = hash of the verifier, `login`, `expiration`, `ip`, `ua`, parsed
  `browser`/`device_os` via the audit-log `UserAgent` parser, and
  `is_current` for the requesting admin's own session).
- `destroy_session( int $user_id, string $token_id ): bool`
- `destroy_user( int $user_id ): bool` — `destroy_all()` for one user.
- `destroy_users( int[] $user_ids ): int` — bulk; returns affected count.
- `destroy_all( int[] $exclude = [] ): int` — everyone; supports excluding the
  current admin so they aren't logged out mid-action.

Every destroy fires `do_action( 'wplalr_session_destroyed', $user_id, $context )`
so the audit logger can record a `forced_logout` row (see §6).

### 3.2 `includes/REST/SessionsController.php` (new)
`WP_REST_Controller`, namespace `wplalr/v1`, base `sessions`, cap
`manage_options`, registered alongside the other controllers in
`WpLoginLogoutRedirect::register_rest_routes()`:
- `GET    wplalr/v1/sessions` → paginated list (args from §3.1).
- `DELETE wplalr/v1/sessions/(?P<user>\d+)/(?P<token>[\w]+)` → one session.
- `DELETE wplalr/v1/sessions/(?P<user>\d+)` → all of one user's sessions.
- `POST   wplalr/v1/sessions/bulk-destroy` → body `{ user_ids: [], exclude_self }`.
- `POST   wplalr/v1/sessions/destroy-all` → everyone (honors `exclude_self`).

All write routes: `manage_options` + nonce (cookie auth, like existing
controllers), input sanitized, user IDs validated against existing users.

### 3.3 Wiring
- `init_classes()`: add `session_repository`, `sessions_controller`.
- No DB migration, no new options, no cron — this feature is **stateless**; it
  reads/acts on core session meta directly. (The only persisted side effect is an
  optional audit-log row, §6.)

---

## 4. React admin UI (`src/`)

**Delivery: a dedicated submenu page rendered as one view of the shared React
app** — see [`admin-subpages-plan.md`](admin-subpages-plan.md). A **Logged-in
Users** submenu under Redirect Options (`admin.php?page=wplalr_sessions`), mount
node `#wplalr-sessions`. The single `admin/script` bundle is enqueued on this
screen and its mount-node dispatcher renders `<SessionsApp>` — no separate
webpack entry/bundle. The enqueue localizes `currentUserId` so the app can
badge/guard the admin's own session.

- **`<SessionsApp>`** (new view) — mounted on `#wplalr-sessions` by the
  `src/admin.js` dispatcher, wrapped in the shared `PageShell` (header +
  cross-page nav). Single view, no internal router.
- **`src/components/SessionsViewer.js`** (new):
  - **Toolbar:** search box, role filter `SelectControl`, **Refresh** button,
    and a **Force logout everyone** button (destructive, behind a confirm
    `Modal`, with an "exclude me" default-checked option).
  - **Table:** checkbox column (row select for bulk), user (avatar + name +
    email), role(s), login time (`human_time_diff`), expires-in, IP, browser/OS,
    session count, and a per-row actions menu (**Log out this user**; if a user
    has multiple sessions, an expandable sub-list with **Log out this session**
    per device). The requesting admin's own row/session is badged **"You"** and
    its destroy action shows a confirm warning ("this will log you out").
  - **Bulk bar:** when rows are selected, show "Force logout selected (N)".
  - **Empty state** when no active sessions.
- **`src/hooks/useSessions.js`** (new) — `apiFetch` against
  `/wplalr/v1/sessions` with pagination/search/filter state, plus the destroy
  mutations; re-fetches after each action. Reuse the `@wordpress/notices`
  snackbar pattern for confirmations/results.
- Pro slots via `@wordpress/hooks`: `wplalr_session_columns`,
  `wplalr_session_row_actions`.

---

## 5. Safeguards & UX

- **Don't silently self-logout.** "Force logout everyone" and bulk actions
  default to **excluding the current admin**; logging out your own session is
  possible but always behind an explicit confirm.
- **Confirm destructive actions** (everyone / bulk / self) with a `Modal`.
- **Capability + nonce** on every write; bulk/everyone are heavier so consider a
  modest server-side guard (e.g. cap re-check) and clear result counts.
- **Pagination + expired-token filtering** so a site with many users/sessions
  doesn't load everything at once or show stale rows.
- **No raw tokens client-side** — expose only a non-reversible `token_id`.

---

## 6. Audit-log integration (optional, Free)

If the audit-log feature ([`audit-logs-plan.md`](audit-logs-plan.md)) is present
and logging is enabled, the `Logger` hooks `wplalr_session_destroyed` and writes a
row with `event = 'forced_logout'`, `description` noting the actor (admin) and
scope (self/user/bulk/all). Cleanly degrades — Sessions works with or without the
logs feature; the two are independent but compose.

---

## 7. Testing / acceptance

- `composer phpcs` + `php -l` clean; `npm run build` + `npm run lint:js` clean.
- Manual (extend [`browser-test-cases.md`](browser-test-cases.md)):
  - Log in as two users in two browsers → both appear with correct IP/browser.
  - Force logout one user → their next request bounces to login; row disappears
    on refresh.
  - Multi-session user (two devices) → can kill one device, the other survives.
  - Bulk select + force logout → all selected ended; counts reported.
  - "Force logout everyone (exclude me)" → all others ended, admin stays logged
    in; without exclude → admin is logged out after confirm.
  - Expired sessions don't show; pagination + search + role filter work.
  - With audit logs on, a `forced_logout` row is recorded.

---

## 8. File checklist

**New PHP**
- `includes/Sessions/SessionRepository.php`
- `includes/REST/SessionsController.php`

**Changed PHP**
- `includes/WpLoginLogoutRedirect.php` (container wiring + REST registration)
- `includes/Logs/Logger.php` (optional `wplalr_session_destroyed` listener — only
  if audit logs land first)

**New React**
- `src/components/SessionsApp.js` (new view, rendered by the `src/admin.js`
  dispatcher — no new webpack entry)
- `src/components/SessionsViewer.js`
- `src/hooks/useSessions.js`

**Changed build / PHP (see [`admin-subpages-plan.md`](admin-subpages-plan.md))**
- `webpack.config.js` — none (single entry)
- `src/admin.js` (register the `#wplalr-sessions` → `<SessionsApp>` mount)
- `includes/Settings.php` (Logged-in Users submenu + mount node)
- `includes/Assets.php` (enqueue `admin/script` on the Sessions screen +
  `currentUserId`)

**Docs**
- `readme.txt` (feature + changelog)
- `docs/extensibility.md` (new filters/actions)
- `docs/browser-test-cases.md` (Sessions cases)

---

## 9. Build order

1. **`SessionRepository`** (list + all destroy variants) against core session
   tokens — verify via REST/direct calls, no UI.
2. **`SessionsController`** REST routes + safeguards (self-exclusion, validation).
3. **React Sessions tab:** table, search/filter/pagination, per-row + bulk +
   everyone actions, confirms, current-session badging.
4. **Audit-log `forced_logout` integration** (only if logs feature exists) +
   docs + extension filters.

---

## 10. Open questions (recommended defaults)

- Default scope of "Force logout everyone": exclude current admin? →
  **Yes, exclude self by default** (opt-in checkbox to include self).
- Show one row per **user** (with expandable sessions) or one row per
  **session/device**? → **Per user, expandable to sessions** (cleaner for the
  common single-session case; power users expand). 
- Pagination strategy on large sites: query distinct `user_id`s from `usermeta`
  vs. scanning all users? → **Distinct `user_id` from `usermeta`** (only users
  with sessions; far smaller set).
- Record forced logouts in the audit log? → **Yes when logs are enabled**
  (`forced_logout` event); no-op otherwise.
