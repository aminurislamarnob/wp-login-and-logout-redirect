# Browser test cases (Claude-in-Chrome)

Manual/automated UI test plan for the React admin settings, written so it can be
driven by the Claude-in-Chrome browser tools. Each case lists the browser
actions and how to verify the outcome (screenshot, `wp-cli`, or console).

## Last run

**2026-06-24 — all cases PASS.** TC-01…TC-15 (admin UI) run in Chrome as `admin`;
TC-B1…TC-B4 (behavioral) run as a real subscriber login (`user`, ID 4) on
`woocommerce.test`. Per-case results are recorded inline below. Notable findings
from that run are collected under [Run notes](#run-notes).

## Environment & setup

- **Settings URL:** `https://woocommerce.test/wp-admin/admin.php?page=wplalr_login_logout_redirect`
- **Auth:** must be logged in as an admin (`manage_options`). If the page shows the
  WP login form, stop and ask the user to log in.
- **Screen id:** `toplevel_page_wplalr_login_logout_redirect` (admin bundle only
  enqueues here).
- **Tooling:** start with `tabs_context_mcp`, then `navigate`; capture state with
  `computer` screenshots; debug with `read_console_messages`.

### State helpers (run in the project dir via shell)

```bash
# Inspect stored rules
wp option get wplalr_redirect_rules --format=json

# Inspect the default fallback URLs
wp option get wplalr_login_redirect
wp option get wplalr_logout_redirect

# Reset to a clean slate before/after a run
wp option update wplalr_redirect_rules '[]' --format=json
```

### Pass criteria for every case

- No uncaught errors in `read_console_messages` (the benign
  `InvalidStateError: Transition was aborted...` at `:0:0` is a Chrome
  view-transition artifact and may be ignored — it has no plugin stack).
- No PHP critical-error snackbar ("There has been a critical error on this website").

---

## TC-01 — Page loads with both tabs

**Steps**
1. `navigate` to the settings URL.
2. Screenshot.

**Expected**
- Header "WP Login and Logout Redirect" with Documentation + Support Me buttons.
- Two tabs: **Redirects** (active) and **Rules**.
- Defaults card titled "Default Redirect URLs" with Login/Logout URL fields and the
  placeholder hint `Placeholders: {{username}}, {{user_slug}}, {{website_url}}`.

**Result:** ✅ PASS (2026-06-24) — header, both tabs, defaults card and hint all present.

## TC-02 — Save default redirect URLs

**Steps**
1. On the Redirects tab, set Login URL = `https://woocommerce.test/after-login`,
   Logout URL = `https://woocommerce.test/after-logout`.
2. Click **Save Changes**. Screenshot.

**Expected**
- "Settings saved successfully!" snackbar.
- `wp option get wplalr_login_redirect` → `https://woocommerce.test/after-login`.
- `wp option get wplalr_logout_redirect` → `https://woocommerce.test/after-logout`.

**Result:** ✅ PASS (2026-06-24) — snackbar shown, both options persisted.

## TC-03 — Switch to Rules tab (empty state)

**Steps**
1. Click the **Rules** tab (URL hash becomes `#/rules`).
2. Screenshot.

**Expected**
- "Redirect Rules" header with explanatory text.
- Empty state: "No rules yet. Add a rule to redirect specific roles, users, or capabilities."
- **Add rule** and **Save Rules** buttons visible.

**Result:** ✅ PASS (2026-06-24) — empty-state message, hash `#/rules`, both buttons visible.

## TC-04 — Add a rule with a role condition

**Steps**
1. Click **Add rule**. A rule card appears (drag handle, name field, Enabled toggle, delete).
2. Type a name, e.g. `Editors to dashboard`.
3. Click **Add condition** → a condition row appears with **When = Role** and a Roles field.
4. Click the Roles field, type `Edit`, and pick the **Editor** suggestion.
5. Set **Login redirect URL** = `https://woocommerce.test/editor-home/`.
6. Click **Save Rules**. Screenshot.

**Expected**
- Role autocomplete shows "Editor" while typing (roles are localized into the page).
- Selected role renders as a token chip "Editor ×".
- "Redirect rules saved!" snackbar.
- `wp option get wplalr_redirect_rules --format=json` contains one rule with a
  server-assigned UUID `id`, `enabled: true`, the label, `conditions: [{type:"role",
  values:["editor"]}]` (stored as the **slug**, not the display name), and the
  login URL. `logout_url` empty.

**Result:** ✅ PASS (2026-06-24) — autocomplete + token chip worked; stored UUID,
`role:["editor"]` (slug), login URL, empty `logout_url`.

## TC-05 — Persistence / round-trip on reload

**Steps**
1. Full-reload the settings URL with `#/rules` (use `navigate`, not just a hash change,
   to force a fresh fetch).
2. Screenshot.

**Expected**
- The saved rule re-renders: name, the **Editor** token (slug→name mapping on load),
  and the login URL all intact.

**Result:** ✅ PASS (2026-06-24) — name, Editor token, and URL re-rendered after fresh navigate.

## TC-06 — Capability condition (free-text tokens)

**Steps**
1. On a rule, set a condition **When = Capability**.
2. In the field, type `edit_pages` and press Enter; add `manage_options`.
3. Save. Verify option.

**Expected**
- Both capabilities stored as `conditions: [{type:"capability", values:["edit_pages","manage_options"]}]`.

**Result:** ✅ PASS (2026-06-24) — both capability tokens stored. Switching a condition's
**When** type clears the previous values field.

## TC-07 — Specific-user condition (async search)

**Steps**
1. Add a condition **When = Specific user**.
2. Type part of a username/display name; pick a suggestion formatted `Name (#id)`.
3. Save. Verify option.

**Expected**
- Suggestions load from `/wp/v2/users` as you type (debounced).
- Stored value is the numeric user **ID** (string), e.g. `values:["9"]`.
- On reload, the token shows the user label, not a bare id.

**Result:** ✅ PASS (2026-06-24) — `admin (#1)` suggestion; stored `values:["1"]`;
on reload the token still showed the `admin (#1)` label.

## TC-08 — Multi-condition AND

**Steps**
1. On one rule, add two conditions: Role = `Editor` AND Capability = `edit_pages`.
2. Save and verify the option holds both conditions in the `conditions` array.

**Expected**
- Rule stores both conditions; runtime requires **all** to match (AND). (Behavioral
  match is covered by the PHP unit harness, not the browser.)

**Result:** ✅ PASS (2026-06-24) — both `role` and `capability` conditions stored on the rule.

## TC-09 — Add a second rule and drag-reorder

**Steps**
1. Add a second rule named `Second rule`.
2. Scroll so both rule cards' drag handles (the `≡` icon) are visible.
3. `left_click_drag` the second rule's handle above the first rule.
4. Screenshot, then **Save Rules**.

**Expected**
- After drag, "Second rule" appears first in the list.
- After save, `wp option get wplalr_redirect_rules --format=json` shows the new order
  (Second rule at index 0).

**Result:** ✅ PASS (2026-06-24) — reordered; `Second rule` at index 0 after save.
**Automation note:** a pixel `left_click_drag` is unreliable here because a tall rule
card pushes both handles outside one viewport. The handle is keyboard-operable
(dnd-kit): focus the **Reorder rule** button, press `Space` to pick up, `Up`/`Down`
to move, `Space` to drop. That path was used and works.

## TC-10 — Toggle Enabled off

**Steps**
1. Turn a rule's **Enabled** toggle off. Save. Verify option.

**Expected**
- Rule persists with `enabled: false`. (Disabled rules are skipped at runtime.)

**Result:** ✅ PASS (2026-06-24) — rule persisted with `enabled: false`.

## TC-11 — Delete a rule

**Steps**
1. Click the trash icon on a rule card. The card disappears immediately.
2. Save. Verify option.

**Expected**
- The deleted rule is gone from the list and from `wplalr_redirect_rules` after save.

**Result:** ✅ PASS (2026-06-24) — card removed immediately (no confirm dialog), gone
from the option after save.

## TC-12 — Delete all rules → empty state

**Steps**
1. Delete every rule, then **Save Rules**.
2. Screenshot.

**Expected**
- Empty-state message returns; `wp option get wplalr_redirect_rules` → `[]`.

**Result:** ✅ PASS (2026-06-24) — empty-state message returned; option `[]`.

## TC-13 — Placeholder hint present on rule cards

**Steps**
1. On any rule card, confirm the hint under the URL fields.

**Expected**
- Monospace line: `Placeholders: {{username}}, {{user_slug}}, {{website_url}}`.

**Result:** ✅ PASS (2026-06-24) — hint present on every rule card.

## TC-14 — No-op extension filters don't break the UI

**Steps**
1. With no Pro plugin active, exercise TC-03/TC-04.

**Expected**
- `wplalr_tabs`, `wplalr_routes`, `wplalr_header_actions`, `wplalr_condition_types`,
  `wplalr_condition_value_control`, `wplalr_rule_fields` all return defaults; the UI is
  unchanged and no console errors appear.

**Result:** ✅ PASS (2026-06-24) — UI unchanged. Console showed only two benign React
Router v7 future-flag **warnings** (`v7_startTransition`, `v7_relativeSplatPath`);
no errors, no PHP critical-error snackbar.

## TC-15 — Server-side validation hardening

**Steps**
1. (Optional, via REST/console) POST to `wplalr/v1/settings` a rule with a bogus role
   (`values:["not_a_role"]`) and a bogus user id (`values:["999999"]`).

**Expected**
- Invalid values are dropped server-side (role not in `wp_roles()`, user id not found);
  the saved option contains only valid values. No fatal error.

**Result:** ✅ PASS (2026-06-24) — status 200; `not_a_role` and `999999` dropped,
`editor`/`1` kept; no fatal.
**Note:** the rules array is sent/received under the param key **`rules`** (not
`redirect_rules`). A convenient way to exercise the exact sanitizer without a browser
nonce is `wp eval` + `rest_do_request`:

```php
wp_set_current_user( 1 );
$req = new WP_REST_Request( 'POST', '/wplalr/v1/settings' );
$req->set_header( 'Content-Type', 'application/json' );
$req->set_body( wp_json_encode( array( 'rules' => array( /* rule(s) */ ) ) ) );
$res = rest_do_request( $req );
// $res->get_data()['rules'] holds the sanitized result.
```

---

## Behavioral redirect checks (optional, needs a second account)

These verify the actual redirect, not just the admin UI. They require logging in/out,
so run in a separate browser profile or incognito to avoid disturbing the admin session.

> **Can't enter passwords?** If the automation harness cannot type credentials, you can
> still verify the same code path without a browser login by calling the plugin's
> redirect resolver directly (see the [Run notes](#run-notes) — "Filter-level
> verification"). The browser run below is the genuine end-to-end check.

## TC-B1 — Role rule redirects on login

**Setup:** a rule matching role `subscriber` → login URL `…/welcome/`.

**Steps**
1. In a clean profile, log in as a subscriber via `wp-login.php`.

**Expected**
- Lands on `…/welcome/` (rule wins over the default).

**Result:** ✅ PASS (2026-06-24) — subscriber `user` landed on
`http://woocommerce.test/welcome/` (404 page is fine — only the redirect target matters).

## TC-B2 — Default fallback on login

**Setup:** no rule matches the logging-in user; default login URL set.

**Expected**
- Lands on the default login URL.

**Result:** ✅ PASS (2026-06-24) — with only an `administrator` rule, subscriber `user`
fell back to the default `http://woocommerce.test/after-login`.

## TC-B3 — Placeholder expansion

**Setup:** a rule login URL `{{website_url}}/u/{{username}}/`.

**Expected**
- Redirect resolves to `https://woocommerce.test/u/<login>/`.

**Result:** ✅ PASS (2026-06-24) — resolved to `http://woocommerce.test/u/user/`
(`{{website_url}}` + `{{username}}` expanded).

## TC-B4 — Loop guard

**Setup:** set a login redirect to the login page URL (`…/wp-login.php`).

**Expected**
- The loop guard blocks it; the user lands on `admin_url()` instead of bouncing.

**Result:** ✅ PASS (2026-06-24) — the user did **not** bounce back to `wp-login.php`
(the loop the guard prevents). The plugin resolved to `admin_url()` as designed.
**Caveat:** for a subscriber, WooCommerce then redirects `/wp-admin/` → `/my-account/`
(customers/subscribers are blocked from admin), so the observed final URL was
`…/my-account/`, not `/wp-admin/`. Testing with a user who *can* access admin would land
on `/wp-admin/` directly.

---

## Run notes

Observations from the 2026-06-24 run that aren't obvious from the steps:

- **TC-09 drag-reorder:** prefer dnd-kit keyboard reordering (focus handle → `Space` →
  arrows → `Space`) over a pixel drag; tall cards put both handles out of one viewport.
- **TC-15 param key:** the REST rules param is **`rules`**, not `redirect_rules`. The
  response also returns rules under `rules`.
- **StoreSuite interferes with filter-level testing.** Another active plugin
  (`storesuite`) hooks `login_redirect` and calls `wp_safe_redirect()` inside the
  callback, so firing `apply_filters( 'login_redirect', … )` in WP-CLI aborts the run
  ("Some code is trying to do a URL redirect"). In a real browser login our redirect
  still wins (confirmed in TC-B1–B3).
- **Filter-level verification (no password needed).** To check redirect logic without a
  browser login, construct the plugin's resolver and call it directly — this bypasses
  other plugins on the hook:

  ```php
  use PluginizeLab\WpLoginLogoutRedirect\{Redirection, RuleEngine, Placeholders};
  $redir = new Redirection( new RuleEngine(), new Placeholders() );
  $user  = get_user_by( 'id', 4 ); // a subscriber
  echo $redir->login_redirect( admin_url(), '', $user );
  ```

  Set `wplalr_redirect_rules` / `wplalr_login_redirect` per scenario first, and restore
  them afterward. This is a code-level equivalent of TC-B1–B4, not a real login.
- **Snackbars auto-dismiss.** After clicking a Save button, screenshot promptly (≤1s) or
  the success snackbar may have already faded.

## Regression checklist (run before release)

- [ ] `composer phpcs` → 0 errors / 0 warnings.
- [ ] `npm run lint:js` → exit 0.
- [ ] `npm run build` → compiles; `assets/build/admin/script.asset.php` lists
      `wp-components`, `wp-api-fetch`, `wp-data`, `wp-element`, `wp-i18n`,
      `wp-notices`, `wp-url`, `wp-hooks`.
- [ ] TC-01 … TC-15 pass with no plugin console errors.
- [ ] TC-B1 … TC-B4 pass (real login or filter-level verification).
