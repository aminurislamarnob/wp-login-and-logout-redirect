# Admin Submenu Pages — separate React apps

Status: **Spec / planning** (no code yet). Cross-cutting plumbing for shipping
**Audit Logs** and **Logged-in Users** as their **own WordPress submenu pages**,
each a separate React app — not tabs inside the existing settings SPA.

Supersedes the "new tab in the existing SPA" UI approach in
[`audit-logs-plan.md`](audit-logs-plan.md) §5 and
[`logged-in-users-plan.md`](logged-in-users-plan.md) §4. Those features' PHP
(repositories, REST controllers, loggers) are unchanged; only their **admin-page
delivery** changes to a dedicated submenu page + dedicated bundle.

---

## 1. Target menu structure

Today: one top-level menu (`add_menu_page`, slug `wplalr_login_logout_redirect`,
screen `toplevel_page_wplalr_login_logout_redirect`) renders `#wplalr-settings`
and mounts the `admin/script` bundle (Redirects + Rules tabs).

Target: keep the top-level page, add two submenu pages under it.

```
Redirect Options (top-level, parent)        all three share the admin/script bundle
├── Redirect Options   → admin.php?page=wplalr_login_logout_redirect   (#wplalr-settings)
├── Audit Logs         → admin.php?page=wplalr_audit_logs              (#wplalr-audit-logs)
└── Logged-in Users    → admin.php?page=wplalr_sessions                (#wplalr-sessions)
```

Each submenu page is a **distinct view**, mounted on its own root element. All
three views ship in **one shared webpack bundle** (`admin/script`) enqueued on
all three screens; the app renders whichever view's mount node is present on the
current page (see §3 and §5). The pages are still separate WP URLs (full page
loads between them) — only the JS bundle is shared.

---

## 2. PHP — menu registration (`includes/Settings.php`)

`Settings::login_logout_redirect_menu()` keeps the `add_menu_page` call, then adds
submenu pages and **captures the returned hook suffixes** (so `Assets` can match
screens without hardcoding screen-id strings):

```php
$main = add_menu_page( /* …unchanged… */ 'wplalr_login_logout_redirect', [ $this, 'render_settings_page' ], 'dashicons-randomize' );

// First submenu duplicates the parent with a cleaner label.
add_submenu_page( 'wplalr_login_logout_redirect', __( 'Redirect Options', … ), __( 'Redirect Options', … ), 'manage_options', 'wplalr_login_logout_redirect', [ $this, 'render_settings_page' ] );

$logs     = add_submenu_page( 'wplalr_login_logout_redirect', __( 'Audit Logs', … ),       __( 'Audit Logs', … ),       'manage_options', 'wplalr_audit_logs', [ $this, 'render_audit_logs_page' ] );
$sessions = add_submenu_page( 'wplalr_login_logout_redirect', __( 'Logged-in Users', … ),  __( 'Logged-in Users', … ),  'manage_options', 'wplalr_sessions',   [ $this, 'render_sessions_page' ] );
```

- Expose the three hook suffixes (e.g. via a `Settings::get_page_hooks()` getter
  or a shared constant/option) so `Assets` enqueues per screen.
- Render callbacks each echo their own mount node:
  - `render_settings_page()` → `<div id="wplalr-settings"></div>` (unchanged).
  - `render_audit_logs_page()` → `<div id="wplalr-audit-logs"></div>`.
  - `render_sessions_page()` → `<div id="wplalr-sessions"></div>`.
- Optionally gate the Audit Logs / Sessions submenus on their feature toggles
  (e.g. only show Audit Logs when logging exists). Recommended: always show; the
  page itself handles the disabled/empty state.

---

## 3. Build — single entry (`webpack.config.js`)

`webpack.config.js` stays **single-entry** — no change:

```js
entry: {
  'admin/script': './src/admin.js',   // Redirects + Rules + Audit Logs + Sessions
},
```

The one bundle contains all three views. `src/admin.js` is the single entry
point; on boot it mounts whichever view's root element is present on the current
WP screen (see §5). `@wordpress/scripts` still emits one `admin/script.asset.php`
(deps + version) and one `admin/script.css`, all of which we already enqueue.

**Why one bundle, not three:** the submenu pages are separate WP URLs (full page
loads), but they need not be separate JS builds. Three entries would mean three
`*.asset.php`/`*.css` pairs, a multi-entry webpack config, a screen→bundle
enqueue map, and the `splitChunks`-vs-duplication question for the shared chrome.
A single bundle removes all of that: shared modules are imported once with no
duplication, and the bundle is already warm in the browser cache when the admin
navigates to the second page. For an admin plugin this size the extra per-page
payload (code for the other two views) is negligible. Only split into per-page
bundles if one view's JS later grows large enough to be worth withholding from
the other screens.

**Shared code:** still extract the reusable chrome — `SettingsHeader`, `icons`,
`LayoutStyles.css`, the snackbar/notices wiring, an `apiFetch` helper, and the
new `PageShell` — into `src/shared/` for tidiness. With one bundle this is purely
organizational (no duplication to avoid), but it keeps the per-view modules thin.

---

## 4. Assets — per-screen enqueue (`includes/Assets.php`)

`enqueue_admin_scripts()` currently early-returns unless the screen is the
top-level page. Generalize the guard to the **set of our three screens** (the
hook suffixes captured in §2) — but enqueue the **same** `admin/script` bundle on
all of them:

```php
$our_screens = [
  $hooks['settings'],
  $hooks['audit_logs'],
  $hooks['sessions'],
];
if ( ! in_array( $screen->id, $our_screens, true ) ) { return; }
```

Then enqueue `assets/build/admin/script.js` (using `admin/script.asset.php` for
deps/version) + `admin/script.css` + `wp-components` — the existing enqueue body,
essentially unchanged; just the screen guard widens. No per-bundle map and no
`enqueue_app()` indirection are needed, since there is one bundle.

**Localized data** (`window.wplalrAdmin`) stays shared (`restRoot`, `roles`,
`homeUrl`). The per-page extras the views need (e.g. `currentUserId` for the
Sessions view's self-badging/guard, retention/notification defaults for the Logs
view) can simply be localized **on every screen** — they're small and the single
bundle is loaded everywhere anyway — or gated on `$screen->id` if you prefer to
keep the payload minimal. Recommended: **localize them unconditionally** for
simplicity; the app reads what the active view needs.

---

## 5. React — one app, three views (`src/`)

A single entry point mounts whichever view's root element exists on the current
WP screen. Each page renders exactly one of the three mount nodes (§2), so the
loop finds at most one match:

```js
// src/admin.js  (the single entry)
const views = {
  'wplalr-settings':   SettingsApp,    // Redirects + Rules (existing)
  'wplalr-audit-logs': AuditLogsApp,   // LogsViewer + useLogs
  'wplalr-sessions':   SessionsApp,    // SessionsViewer + useSessions
};

for ( const [ id, App ] of Object.entries( views ) ) {
  const el = document.getElementById( id );
  if ( el ) {
    createRoot( el ).render( <PageShell><App /></PageShell> );
    break;
  }
}
```

- **`<SettingsApp>`** (existing Redirects + Rules SPA, factored out of the current
  `src/admin.js` body) — keeps its internal Redirects/Rules hash tabs.
- **`<AuditLogsApp>`** (new) — `LogsViewer` + its `useLogs` hook from the
  audit-logs plan, as a standalone view rather than a routed tab.
- **`<SessionsApp>`** (new) — `SessionsViewer` + `useSessions`.

The three view components live under `src/components/` (or `src/views/`); there
are **no** separate `src/audit-logs.js` / `src/sessions.js` entry files — only
the one `src/admin.js` entry.

**Cross-page navigation & visual consistency:** since these are now separate WP
pages (full page loads between them), drop the in-app hash tabs for cross-feature
nav. Instead each app reuses a shared `<PageShell>` that renders `SettingsHeader`
+ a secondary nav bar of plain links to the three `admin.php?page=…` URLs,
highlighting the active page. The Redirects app keeps its internal Redirects/Rules
hash tabs (those remain sub-views of one page); Audit Logs and Sessions are single
views, so they need no internal router.

---

## 6. File checklist (delta vs. the feature plans)

**Changed PHP**
- `includes/Settings.php` — add submenu pages + render callbacks + hook-suffix
  getter.
- `includes/Assets.php` — widen the screen guard to our three hook suffixes
  (same `admin/script` bundle on all) + localize the per-view extras.

**Changed build**
- `webpack.config.js` — **none** (stays single-entry).

**New React**
- `src/shared/` — extracted `SettingsHeader`, `icons`, styles, `PageShell`,
  `apiFetch` helper, notices wiring.
- View components/hooks per their own plans: `AuditLogsApp` (`LogsViewer` +
  `useLogs`), `SessionsApp` (`SessionsViewer` + `useSessions`), and `SettingsApp`
  (the existing Redirects + Rules SPA, extracted).

**Changed React**
- `src/admin.js` — becomes the mount-node dispatcher (§5); move the existing
  Redirects/Rules app body into `<SettingsApp>`. Update imports to `src/shared/`.

**Docs**
- This file; UI sections of the two feature plans point here.

---

## 7. Build order

1. **Menu + mounts:** add the two submenu pages and empty mount divs; confirm
   they appear and load (blank) only on their screens.
2. **Assets + dispatcher:** widen the `Assets` screen guard to enqueue
   `admin/script` on all three screens; turn `src/admin.js` into the mount-node
   dispatcher (§5). Confirm each page mounts only its own view.
3. **Shared extraction:** move chrome to `src/shared/`, add `PageShell` +
   cross-page nav; extract the existing app into `<SettingsApp>`.
4. **Feature views:** build `AuditLogsApp` / `SessionsApp` per their plans.

---

## 8. Extensibility & the Pro seam

The single-bundle decision (§3) is **build-time** and applies only to the free
plugin. It does **not** constrain how a future Pro version extends these screens,
because Pro never recompiles the free bundle. Spelling this out so the
architecture stays Pro-ready:

- **Pro ships as a separate plugin** that depends on the free one. (wordpress.org
  forbids bundling paid upgrades in the free repo; licensing/updates run through a
  separate channel.) It is installed alongside, activated independently, and
  cannot touch the free build output.
- **Pro extends at runtime, never at build time.** It enqueues its **own** small
  bundle on these same screens (the hook suffixes are already public via the §2
  getter) and registers `@wordpress/hooks` `addFilter(...)` calls against the
  slots the free views expose — it does not fork or rebuild `admin/script`.
- Therefore the free-vs-Pro split is **orthogonal** to whether the free app is one
  bundle or three. Keep the single bundle; Pro composes on top of it.

**Treat these seams as a public API** (version them; avoid breaking changes):

JS (`@wordpress/hooks`), already named in the feature plans —
- `wplalr_log_columns`, `wplalr_log_row_actions` (Logs view — CSV export, extra
  columns land here in Pro).
- `wplalr_session_columns`, `wplalr_session_row_actions` (Sessions view).

PHP (actions/filters), already named in the feature plans —
- `wplalr_after_resolve` (resolved redirect URL + matched `rule_id`).
- `wplalr_log_recorded`, `wplalr_session_destroyed` (per-event extension points).
- `wplalr_rest_log_item`, `wplalr_rest_logs_query_args` (REST shape extension).

**`PageShell` cross-page nav (§5) should itself be filterable** so Pro can add its
own submenu pages/links into the secondary nav without patching the free app —
e.g. a `wplalr_admin_nav_items` filter feeding the link bar.

**Revisit trigger (the one case to split a bundle):** if a *single view's* JS
grows large — Logs analytics, or a Pro-heavy screen — split that one view into its
own webpack entry then, and enqueue it only on its screen. Designing for that now
is premature; the hook seams above are what actually matter for Pro.

---

## 9. Open questions (recommended defaults)

- Keep a top-level "Redirect Options" page, or convert the top level to a generic
  parent? → **Keep Redirect Options as the landing page** (least disruption;
  existing bookmarks/links still work).
- Separate bundles vs. one bundle mounting different roots per page? →
  **One shared bundle** mounting the matching root per screen (§3, §5). Removes
  multi-entry webpack, the screen→bundle map, and the splitChunks/duplication
  question; per-page payload overhead is negligible for an admin plugin this
  size, and the bundle is cache-warm by the second page. Revisit per-page bundles
  only if one view's JS grows large enough to be worth withholding.
- Cross-page nav as a secondary link bar vs. relying on the WP submenu only? →
  **Secondary link bar** in the shared `PageShell` for discoverability, in
  addition to the WP submenu.
- Gate Audit Logs / Sessions submenus behind feature/enable toggles? → **Always
  visible**; pages render their own disabled/empty states.
