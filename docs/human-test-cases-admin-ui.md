# Human test cases — admin UI redesign & rules UX

Manual test plan for a person clicking through the admin, covering the UI
modernization (`6a56754`) and the rules/settings UX work (`7cf41d7`) on
`feature/react-admin-settings`.

Unlike `docs/browser-test-cases.md` (written to be driven by the Claude-in-Chrome
tools), this doc assumes a human with a browser and no tooling beyond DevTools.

## Environment & setup

- **Settings:** `/wp-admin/admin.php?page=wplalr_login_logout_redirect`
- **Audit Logs:** `/wp-admin/admin.php?page=wplalr_audit_logs`
- **Logged-in Users:** `/wp-admin/admin.php?page=wplalr_logged_in_users`
- Log in as an admin (`manage_options`). Build the bundle first: `npm install && npm run build`.
- Have a second, non-admin user (e.g. a subscriber) and a second browser/incognito
  window available for the behavioral cases.

### Reset to a clean slate

```bash
wp option update wplalr_redirect_rules '[]' --format=json
wp option delete wplalr_login_redirect wplalr_logout_redirect
```

### Applies to every case

- Keep DevTools open on the Console tab. No red uncaught errors should appear.
  (`InvalidStateError: Transition was aborted…` at `:0:0` is a Chrome
  view-transition artifact with no plugin stack — ignore it.)
- No "There has been a critical error on this website" notice.

Record each result as PASS / FAIL with a note.

---

## A. Visual design

### HTC-01 — Header and page nav

1. Open the settings page.
2. Look at the header and the submenu nav.

**Expected:** gradient icon badge next to the plugin title; Documentation and
Support Me buttons present; the current page in the nav has a rounded indicator.
Nothing overlaps or clips.

### HTC-02 — Segmented tabs

1. On the settings page, look at the Redirects / Rules / Others tabs.
2. Click each tab in turn.

**Expected:** tabs render as a segmented pill group (not underlined links). The
active tab is visibly filled. Clicking changes the hash in the URL and swaps the
panel; reloading the page keeps you on the same tab.

### HTC-03 — Stat cards on Audit Logs

1. Open Audit Logs.
2. Inspect the four cards, then hover over each.

**Expected:** Logins, Logouts, Failed logins, Total events — each with its own
accent icon on the right and a color wash. Label sits above the number. Hover
produces a visible state change. Numbers match the table below (see HTC-04).

### HTC-04 — Event badges are color-coded

1. Generate one of each event: log in, log out, then a failed login (wrong
   password on the login form), then force-logout a session from Logged-in Users.
2. Reload Audit Logs.

**Expected:** each row's badge is colored per event type — login, logout, failed
login and forced logout are all visually distinct, not one uniform gray.

### HTC-05 — Toolbar and field consistency

1. Compare the Audit Logs toolbar controls (event filter, search, buttons) side
   by side.
2. Click into a text field on the Redirects tab.

**Expected:** toolbar controls share a uniform 40px height and align on one
baseline. Text fields are rounded and show a brand-colored focus ring on focus
(not the default browser outline).

### HTC-06 — Responsive layout

1. Resize the browser to ~768px wide, then to ~380px.

**Expected:** sections fluidly narrow; at the mobile breakpoint the layout
reflows to a single column. **The page never scrolls horizontally** and no
control is cut off at either width.

---

## B. Rules UX

### HTC-07 — New rule opens expanded

1. Go to Rules → click **Add rule**.

**Expected:** the new card appears **already expanded**, ready to edit. Any
previously expanded cards keep their own state.

### HTC-08 — Collapsed summary is accurate

1. On a rule with a name, two conditions and a login URL, collapse it.

**Expected:** the collapsed header shows the priority number, the rule name, and
a summary reading `2 conditions · → <login URL>`.
2. Remove all conditions and collapse again.

**Expected:** the summary now reads `Applies to everyone`.
3. Clear the rule name.

**Expected:** the title falls back to `Untitled rule`.

### HTC-09 — Expand/collapse interaction

1. Click anywhere on the collapsed header (not on a control).
2. Click the chevron button.

**Expected:** both toggle the card. The chevron flips between down and up, and
the expand/collapse is animated rather than snapping.

### HTC-10 — Header controls don't toggle the card

1. On a collapsed card, click the **Enabled** toggle.
2. Click and drag the drag handle.

**Expected:** the toggle flips the enabled state and the card **stays collapsed**
— the click must not bubble up and expand it. Dragging reorders without
expanding. A disabled rule is visually de-emphasized.

### HTC-11 — Delete from the header

1. Click the trash icon on a rule card.

**Expected:** that rule is removed, the card does not expand on the way out, and
the priority numbers on the remaining cards renumber to stay sequential from 1.

### HTC-12 — Priority reflects evaluation order

1. With three rules, drag the third to the top.
2. Hover the priority badge.

**Expected:** badges renumber to 1, 2, 3 top-to-bottom immediately after the
drop. The tooltip reads "Evaluation order — the first matching rule wins."
3. Save, reload the page.

**Expected:** the new order persists.

### HTC-13 — Conditions panel and AND connectors

1. Expand a rule with no conditions.

**Expected:** a tinted panel with the empty text "No conditions — this rule
applies to everyone…" and an **Add condition** soft button.
2. Add three conditions.

**Expected:** an `AND` connector appears **between** rows — before rows 2 and 3,
but not above the first row.
3. Delete the middle condition.

**Expected:** two rows remain with exactly one AND between them.

### HTC-14 — Placeholder copy chips

1. Expand a rule and find the `Placeholders:` chips under the URL fields.
2. Click `{{username}}`.

**Expected:** the chip text swaps to **Copied!** and reverts after ~1.5s. Paste
into a URL field — you get `{{username}}`.
3. Click a second chip while the first still reads "Copied!".

**Expected:** only the newly clicked chip shows "Copied!"; the first reverts.
4. Confirm the same chips appear on the **Redirects** tab under the default URLs.

### HTC-15 — Copy fallback on a non-secure origin

1. Load the admin over plain `http://` (not HTTPS), where `navigator.clipboard`
   is unavailable.
2. Click a placeholder chip.

**Expected:** copy still works via the textarea fallback — chip shows "Copied!",
paste yields the token, and no page jump/scroll occurs. Skip this case if your
local site is HTTPS-only.

### HTC-16 — Token suggestions overlay

1. On a rule, add a condition whose value control is a token field (e.g. Role).
2. Click the field and type a letter.

**Expected:** suggestions float **over** the content as a dropdown — the field
itself does not grow and push the card taller. Hovering a suggestion shows a
brand hover state. Picking one adds a brand-colored pill to the field.
3. Add enough tokens to wrap onto a second line.

**Expected:** the card grows gracefully; nothing overlaps the field below.

---

## C. Behavior (make sure the redesign didn't break the engine)

### HTC-17 — Rules still save and round-trip

1. Build a rule: name it, add a Role = Subscriber condition, set a login URL.
2. Save, then hard-reload.

**Expected:** everything comes back exactly as entered. Verify server-side:
   ```bash
   wp option get wplalr_redirect_rules --format=json
   ```

### HTC-18 — Placeholders survive save

1. Set the default login URL to `{{website_url}}/hello/{{username}}/` and save.
2. Reload.

**Expected:** the `{{…}}` tokens are still literally present — not stripped,
escaped or URL-encoded.

### HTC-19 — A rule actually redirects

1. Keep the HTC-17 subscriber rule enabled with a distinct login URL.
2. In a second browser/incognito, log in as the subscriber.

**Expected:** you land on the rule's URL, not the dashboard.
3. Disable the rule via the collapsed card's toggle, save, log in again.

**Expected:** the rule no longer applies; the default fallback is used.

### HTC-20 — Keyboard and screen reader pass

1. From the top of the Rules tab, Tab through a rule card.

**Expected:** the drag handle, Enabled toggle, delete and chevron buttons are all
reachable and show a visible focus ring. Enter/Space on the chevron toggles the
card. Placeholder chips are reachable and copy on Enter.

> **Known gap:** the collapsed header is click-to-expand via `role="presentation"`
> and is not itself a keyboard target — the chevron button is the keyboard path.
> Flag it if you think that's not good enough, but it is not a regression.

---

## Result log

| Case | Result | Notes |
|---|---|---|
| HTC-01 | | |
| HTC-02 | | |
| HTC-03 | | |
| HTC-04 | | |
| HTC-05 | | |
| HTC-06 | | |
| HTC-07 | | |
| HTC-08 | | |
| HTC-09 | | |
| HTC-10 | | |
| HTC-11 | | |
| HTC-12 | | |
| HTC-13 | | |
| HTC-14 | | |
| HTC-15 | | |
| HTC-16 | | |
| HTC-17 | | |
| HTC-18 | | |
| HTC-19 | | |
| HTC-20 | | |
