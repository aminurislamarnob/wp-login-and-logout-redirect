# Browser test cases — Admin subpages (Claude-in-Chrome)

Manual/automated UI test plan for the **admin submenu pages** added in
`feature/admin-subpages`: the **Audit Logs** page, the **Logged-in Users** page
(force logout), the **Others** settings tab (logging toggle + retention), and the
shared cross-page navigation. Written so it can be driven by the Claude-in-Chrome
browser tools. Each case lists the browser actions and how to verify the outcome
(screenshot, `wp-cli`, or console).

Companion to [`browser-test-cases.md`](browser-test-cases.md), which covers the
Redirects + Rules SPA. This file only covers the new subpages.

## Environment & setup

- **Redirect Options (settings):** `https://woocommerce.test/wp-admin/admin.php?page=wplalr_login_logout_redirect`
- **Audit Logs:** `https://woocommerce.test/wp-admin/admin.php?page=wplalr_audit_logs`
- **Logged-in Users:** `https://woocommerce.test/wp-admin/admin.php?page=wplalr_sessions`
- **Auth:** must be logged in as an admin (`manage_options`). If a page shows the
  WP login form, stop and ask the user to log in.
- **Mount nodes:** Settings → `#wplalr-settings`, Audit Logs → `#wplalr-audit-logs`,
  Logged-in Users → `#wplalr-sessions`. All three share the one `admin/script`
  bundle; each page mounts only its own view.
- **Tooling:** start with `tabs_context_mcp`, then `navigate`; capture state with
  `computer` screenshots; debug with `read_console_messages`.

### State helpers (run in the project dir via shell)

```bash
# Logging on/off + retention (managed on the Others tab)
wp option get wplalr_enable_logs            # 'yes' | 'no'
wp option get wplalr_logs_retention_days    # integer (days; 0 = keep forever)
wp option update wplalr_enable_logs yes
wp option update wplalr_logs_retention_days 30

# Inspect / wipe the audit-log table
wp db query "SELECT COUNT(*) FROM $(wp db prefix --allow-root 2>/dev/null)wplalr_auth_logs;"
wp db query "TRUNCATE TABLE $(wp db prefix --allow-root 2>/dev/null)wplalr_auth_logs;"

# Seed an event without a real login (fires the logger via wp_login)
wp eval 'do_action( "wp_login", "admin", get_user_by( "id", 1 ) );'
```

### Pass criteria for every case

- No uncaught errors in `read_console_messages` (the benign
  `InvalidStateError: Transition was aborted...` at `:0:0` is a Chrome
  view-transition artifact and may be ignored — it has no plugin stack).
- No PHP critical-error snackbar ("There has been a critical error on this website").
- The browser harness must **not** trigger native `alert`/`confirm` dialogs. All
  destructive flows here use a `@wordpress/components` `<Modal>` (in-page), not a
  browser confirm — so they are safe to drive. Do not click WP core "Empty Trash"
  style buttons elsewhere.

---

## Menu & navigation

## TC-S01 — Submenu pages registered under Redirect Options

**Steps**
1. `navigate` to the settings URL.
2. Hover/expand the **Redirect Options** menu in the WP admin sidebar. Screenshot.

**Expected**
- Three submenu items: **Redirect Options**, **Audit Logs**, **Logged-in Users**.
- Their links point to `admin.php?page=wplalr_login_logout_redirect`,
  `?page=wplalr_audit_logs`, and `?page=wplalr_sessions` respectively.

## TC-S02 — Cross-page nav bar renders on every subpage

**Steps**
1. `navigate` to each of the three URLs in turn; screenshot each.

**Expected**
- Each page shows the shared `PageShell` header ("WP Login and Logout Redirect")
  and a secondary nav bar (`.wplalr-page-nav`) with three links: Redirect Options,
  Audit Logs, Logged-in Users.
- The link for the **current** page has the `is-active` class (purple underline).

## TC-S03 — Nav links perform full-page loads to the right view

**Steps**
1. From Audit Logs, click the **Logged-in Users** nav link.
2. Confirm the URL changes to `?page=wplalr_sessions` and the Sessions view mounts.
3. Click **Redirect Options**; confirm Redirects + Rules SPA mounts.

**Expected**
- Each click is a normal full page load (URL changes, not just a hash).
- Only the destination view's mount node is present; no console errors about a
  missing root.

## TC-S04 — Only the matching view mounts per screen

**Steps**
1. On Audit Logs, run in console:
   `document.getElementById('wplalr-audit-logs') && !document.getElementById('wplalr-sessions') && !document.getElementById('wplalr-settings')`.

**Expected**
- Evaluates truthy — exactly one mount node exists per page (the dispatcher in
  `src/admin.js` mounts only the present root).

---

## Others tab (logging settings)

## TC-S05 — Others tab reachable from the settings SPA

**Steps**
1. `navigate` to the settings URL, then click the **Others** tab (hash `#/others`).
2. Screenshot.

**Expected**
- "Audit Logging" card with an **Enable logging** toggle and a **Delete logs older
  than** select (7 / 30 / 90 days / Keep forever).
- A **Save Changes** button.

## TC-S06 — Enable logging persists

**Steps**
1. Pre-state: `wp option update wplalr_enable_logs no`.
2. On the Others tab, turn **Enable logging** on; click **Save Changes**.
   Screenshot promptly (snackbar auto-dismisses).

**Expected**
- "Settings saved successfully!" snackbar.
- `wp option get wplalr_enable_logs` → `yes`.

## TC-S07 — Retention select persists

**Steps**
1. On the Others tab, set **Delete logs older than** = `90 days`; Save.

**Expected**
- `wp option get wplalr_logs_retention_days` → `90`.
- Set to **Keep forever** and Save → option becomes `0`.

## TC-S08 — Round-trip on reload

**Steps**
1. Set logging on + retention 7 days, Save.
2. Full `navigate` reload of the settings URL `#/others`.

**Expected**
- Toggle re-renders **on**; select re-renders **7 days** (values fetched fresh
  from `/wplalr/v1/settings`).

---

## Audit Logs page

## TC-S09 — Logging OFF empty state

**Steps**
1. `wp option update wplalr_enable_logs no`.
2. `navigate` to the Audit Logs URL. Screenshot.

**Expected**
- Four stat cards (Logins / Logouts / Failed logins / Total events), all `0` when
  the table is empty.
- Empty message: "Logging is off. Enable it on the Others tab under Redirect
  Options to start recording login activity."

## TC-S10 — Logging ON, no rows yet

**Steps**
1. `wp option update wplalr_enable_logs yes`; truncate the log table.
2. `navigate` to Audit Logs. Screenshot.

**Expected**
- Empty message switches to "No log entries match your filters yet."
- Toolbar shows Search, Event filter, **Refresh**, and a disabled **Delete all**
  (disabled because total is 0).

## TC-S11 — Recorded events appear with correct columns

**Steps**
1. With logging on, seed an event:
   `wp eval 'do_action( "wp_login", "admin", get_user_by( "id", 1 ) );'`
   (or perform a real login in a separate profile).
2. `navigate` to Audit Logs (or click **Refresh**). Screenshot.

**Expected**
- A row appears with columns: Time (relative, hover shows full datetime), User
  (`admin`), Event badge (**Login**), IP, Browser / OS, Redirect.
- The **Logins** and **Total events** stat cards increment.

## TC-S12 — Event filter

**Steps**
1. Seed at least one `login` and one `failed` event
   (`wp eval 'do_action( "wp_login_failed", "baduser", new WP_Error() );'`).
2. On Audit Logs, set the Event filter to **Failed login**. Screenshot.

**Expected**
- Only `failed` rows show; the failed badge uses the `is-failed` (red) style and
  may show an error code. Switching back to **All events** restores the full list.

## TC-S13 — Search by username / IP

**Steps**
1. In the Search box type a known username (e.g. `admin`).

**Expected**
- List narrows to matching rows (server-side `username`/`ip` LIKE filter). Clearing
  the box restores all rows.

## TC-S14 — Delete a single entry

**Steps**
1. Click the trash icon on a row.

**Expected**
- The row is removed and the matching stat card decrements.
- `wp db query "SELECT COUNT(*) ..."` reflects one fewer row.

## TC-S15 — Delete all (modal confirm)

**Steps**
1. Click **Delete all**. An in-page `<Modal>` titled "Delete all logs?" appears.
2. Click **Delete all** inside the modal. Screenshot.

**Expected**
- Modal copy: "This permanently removes every recorded event. This cannot be
  undone." **Cancel** closes with no change.
- Confirming empties the table; stat cards reset to 0; empty state returns.
- This is a component Modal, **not** a browser confirm — safe to automate.

## TC-S16 — Pagination

**Steps**
1. Seed > 20 events (the default per_page). 
2. On Audit Logs, confirm the pager appears and **Next**/**Previous** work; the
   "Page X / Y" indicator updates and buttons disable at the ends.

---

## Logged-in Users page

## TC-S17 — Page loads with active sessions

**Steps**
1. Ensure at least one user is logged in (the admin running the test counts).
2. `navigate` to the Logged-in Users URL. Screenshot.

**Expected**
- Toolbar: Search users, Role filter, **Refresh**, **Force logout everyone**.
- A table with columns: checkbox, User (avatar + name + email), Role, Logged in
  (relative), Expires (relative, "in …"), Sessions count, and a **Log out** action.
- The current admin's row shows a **You** badge.

## TC-S18 — Empty state

**Steps**
1. If feasible on a throwaway site, clear all sessions
   (`wp eval 'foreach(get_users() as $u){ WP_Session_Tokens::get_instance($u->ID)->destroy_all(); }'`)
   — **note:** this logs you out too; only do this when you can log back in.

**Expected**
- "No users currently have active sessions." and **Force logout everyone** is
  disabled.

## TC-S19 — Role filter & search

**Steps**
1. Set the Role filter to a specific role; type part of a name/email in Search.

**Expected**
- The list narrows accordingly (server-side). "All roles" + empty search restores.

## TC-S20 — Expand multi-session user

**Steps**
1. Log the same user in from a second browser/profile so `session_count` > 1.
2. On that user's row click **Show**.

**Expected**
- Detail rows expand showing per-session Browser / OS, IP, and an **End** action.
- The session matching the current device shows a **This device** badge.

## TC-S21 — Force logout one user (modal)

**Steps**
1. On a **non-self** user's row click **Log out**. A `<Modal>` "Log out this user?"
   appears.
2. Confirm with **Force logout**.

**Expected**
- Modal message names the user; confirming ends that user's sessions and the row
  drops out (or session count goes to 0) after refresh.
- A `forced_logout` row is recorded in the audit log (verify on Audit Logs page if
  logging is on).
- ⚠️ Do **not** test "Log out" on your own (You) row unless you intend to be
  logged out — the modal warns "you will be logged out immediately."

## TC-S22 — Bulk force logout with "Keep me logged in"

**Steps**
1. Tick the checkboxes for two non-self users (the bulk bar shows "N selected").
2. Click **Force logout selected** → modal. Leave **Keep me logged in** checked.
3. Confirm.

**Expected**
- Selected users' sessions end; the admin's own session is preserved (excludeSelf
  defaults to checked). Selection clears after the action.

## TC-S23 — Force logout everyone (excludeSelf)

**Steps**
1. Click **Force logout everyone** → modal "Force logout everyone?".
2. Keep **Keep me logged in** checked; confirm.

**Expected**
- All other users' sessions are destroyed; the current admin stays logged in (the
  page does not bounce to wp-login). Unchecking "Keep me logged in" would also end
  the admin's session — only do that intentionally.

---

## Extensibility (no-op without Pro)

## TC-S24 — Column / row-action filters default cleanly

**Steps**
1. With no Pro plugin active, load Audit Logs and Logged-in Users.

**Expected**
- `wplalr_log_columns`, `wplalr_session_columns`, and `wplalr_admin_nav_items` all
  return defaults (the only nav/column JS filters wired in this branch); the UI is
  unchanged and no console errors appear. (Row-action seams named in the plan are
  not implemented here yet.)

---

## Regression checklist (run before merge)

- [ ] `composer phpcs` → 0 errors / 0 warnings.
- [ ] `npm run lint:js` → exit 0.
- [ ] `npm run build` → compiles; one `admin/script.*` bundle emitted.
- [ ] TC-S01 … TC-S16 pass (menu, nav, Others, Audit Logs) with no console errors.
- [ ] TC-S17 … TC-S23 pass (Logged-in Users) — run destructive cases on a throwaway
      site or with a second account; never on your own session unintentionally.
- [ ] TC-S24 passes (filters default; no Pro).
