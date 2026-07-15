# Browser/behavioral test cases — Audit-log Notifier (Claude-in-Chrome)

Test plan for `feature/audit-log-notifier`: the **Notifications** card on the
Others settings tab, the **per-event login alerts** (async email), and the
scheduled **activity digest** (daily / weekly / monthly). Written so the UI cases
can be driven by the Claude-in-Chrome browser tools; the email/cron cases are
verified with `wp-cli` + a mail catcher.

Companion to [`browser-test-cases.md`](browser-test-cases.md) (Redirects + Rules)
and [`browser-test-cases-subpages.md`](browser-test-cases-subpages.md) (admin
subpages). Implements the acceptance list in
[`notifier-plan.md`](notifier-plan.md) §8.

## Environment & setup

- **Settings URL:** `https://woocommerce.test/wp-admin/admin.php?page=wplalr_login_logout_redirect`
  → click the **Others** tab (hash `#/others`).
- **Auth:** logged in as an admin (`manage_options`).
- **Gating:** the Notifier hooks nothing unless logging is enabled
  (`wplalr_enable_logs === 'yes'`). Turn logging **on** before any alert/digest case.
- **Mail capture:** these tests assert that `wp_mail()` is *invoked* with the
  right recipient/subject. Use one of:
  - **MailHog** on the Herd site (preferred): read the captured message in its UI
    / API (`http://localhost:8025`).
  - A **`wp_mail` interceptor** (no SMTP needed) — see
    [Mail interceptor snippet](#mail-interceptor-snippet).

### State helpers (run in the project dir via shell)

```bash
# Prereq: logging on
wp option update wplalr_enable_logs yes

# Notifier options (also settable from the UI; these are for setup/inspection)
wp option get wplalr_logs_notify_roles --format=json     # e.g. ["administrator"]
wp option get wplalr_logs_notification_email
wp option get wplalr_logs_digest                          # '' | daily | weekly | monthly

# Cron inspection
wp cron event list | grep wplalr            # cleanup, digest, single-event alerts
wp cron schedule list | grep monthly        # custom 'monthly' schedule present?

# Run a due event immediately
wp cron event run wplalr_logs_digest_send
wp cron event run wplalr_send_login_alert
```

### Pass criteria for every case

- No uncaught errors in `read_console_messages` (ignore the benign
  `InvalidStateError: Transition was aborted...` view-transition artifact).
- No PHP critical-error snackbar.
- Logging in/out for a real alert test must still complete normally — the alert
  send is deferred to a single cron event, so login latency is unaffected.

---

## Notifications card (UI)

## TC-N01 — Notifications card renders below Audit Logging

**Steps**
1. `navigate` to the settings URL, click **Others**. Screenshot.

**Expected**
- A second card titled **Notifications** appears under the Audit Logging card.
- Controls: **Alert on login for roles** (token field), **Notification email**
  (email text field), **Digest summary** (select: Off / Daily / Weekly / Monthly).
- Help text notes both features require logging enabled.

## TC-N02 — Notification email defaults to admin email

**Steps**
1. Pre-state: `wp option delete wplalr_logs_notification_email`.
2. Reload the Others tab.

**Expected**
- The **Notification email** field is pre-filled with the site admin email
  (the REST GET resolves the default from `admin_email`).

## TC-N03 — Save notify roles (token field)

**Steps**
1. Click **Alert on login for roles**, type `Admin`, pick **Administrator**.
2. Click **Save Changes**. Screenshot promptly.

**Expected**
- Role renders as a token chip "Administrator ×".
- "Settings saved successfully!" snackbar.
- `wp option get wplalr_logs_notify_roles --format=json` → `["administrator"]`
  (stored as the **slug**, not the display name).

## TC-N04 — Save notification email + digest cadence

**Steps**
1. Set **Notification email** = `alerts@woocommerce.test`.
2. Set **Digest summary** = **Weekly**. Save.

**Expected**
- `wp option get wplalr_logs_notification_email` → `alerts@woocommerce.test`.
- `wp option get wplalr_logs_digest` → `weekly`.

## TC-N05 — Round-trip on reload

**Steps**
1. After TC-N03/04, full `navigate` reload of the Others tab.

**Expected**
- Role token, email, and digest select all re-render from the saved values
  (slug→name mapping on load for the role token).

## TC-N06 — Invalid email falls back to admin email (server-side)

**Steps**
1. In **Notification email** type `not-an-email`; Save.

**Expected**
- The server sanitizer rejects it and stores the site `admin_email` instead;
  on reload the field shows the admin email, not `not-an-email`. No fatal.

## TC-N07 — Empty roles disables alerts

**Steps**
1. Remove all role tokens from **Alert on login for roles**; Save.

**Expected**
- `wp option get wplalr_logs_notify_roles --format=json` → `[]`. With empty roles
  no alert is ever queued (see TC-N10).

---

## Per-event login alerts (behavioral)

> Set up the mail catcher first ([snippet](#mail-interceptor-snippet) or MailHog).

## TC-N08 — Watched role login queues + sends one alert

**Setup:** logging on; `wplalr_logs_notify_roles = ["administrator"]`;
notification email set.

**Steps**
1. Trigger a login for an administrator. Either a real login in a separate
   profile, or seed the recorded-event path:
   `wp eval 'do_action( "wp_login", "admin", get_user_by( "id", 1 ) );'`
2. Confirm a single event is scheduled:
   `wp cron event list | grep wplalr_send_login_alert` (should show one).
3. Run it: `wp cron event run wplalr_send_login_alert`.
4. Check the mail catcher.

**Expected**
- Exactly **one** `wplalr_send_login_alert` single-event was queued for the login.
- After it runs, one HTML email to the configured recipient with subject
  `[<site>] Login alert: <user> signed in`, body containing the username, time, IP,
  and browser/OS from the re-read log row.

## TC-N09 — Login is not blocked on send (async)

**Steps**
1. With an SMTP delay or a slow `wp_mail` filter, perform a real admin login.

**Expected**
- The login + redirect complete immediately; the email send happens later when the
  single cron event fires (not inline on `wp_login`).

## TC-N10 — Non-matching role → no email

**Setup:** `wplalr_logs_notify_roles = ["administrator"]`.

**Steps**
1. Log in as a **subscriber** (or
   `wp eval 'do_action( "wp_login", "sub", get_user_by( "id", 4 ) );'`).

**Expected**
- No `wplalr_send_login_alert` event is queued; no email is sent.

## TC-N11 — Empty roles → no email

**Setup:** `wplalr_logs_notify_roles = []`.

**Steps**
1. Log in as an administrator.

**Expected**
- No alert queued, regardless of role.

## TC-N12 — Logging off → Notifier inert

**Setup:** `wp option update wplalr_enable_logs no`.

**Steps**
1. Log in as an administrator (with notify roles configured).

**Expected**
- No row recorded, no alert queued, no email (the Notifier returns early when
  logging is off, same guard as the Logger).

---

## Digest (cron)

## TC-N13 — Custom `monthly` schedule registered

**Steps**
1. `wp cron schedule list`.

**Expected**
- A `monthly` schedule (~30 days) appears alongside core
  hourly/twicedaily/daily/weekly.

## TC-N14 — Setting a cadence schedules the event

**Steps**
1. In the UI set **Digest summary** = **Daily**, Save (or
   `wp option update wplalr_logs_digest daily`).
2. `wp cron event list | grep wplalr_logs_digest_send`.

**Expected**
- `wplalr_logs_digest_send` is scheduled with the `daily` recurrence
  (next run ~1 hour out).

## TC-N15 — Changing cadence reschedules

**Steps**
1. Change **Digest summary** Daily → **Weekly**, Save.
2. Re-list the cron event.

**Expected**
- The old daily occurrence is cleared and a single `weekly` occurrence exists
  (no duplicate digest events).

## TC-N16 — Setting Off clears the event

**Steps**
1. Set **Digest summary** = **Off**, Save.
2. `wp cron event list | grep wplalr_logs_digest_send`.

**Expected**
- No `wplalr_logs_digest_send` event remains scheduled.

## TC-N17 — Digest email contents

**Setup:** logging on; seed a known mix of events in the window, e.g.
`wp eval 'do_action("wp_login","admin",get_user_by("id",1)); do_action("wp_login_failed","x", new WP_Error());'`
cadence = `daily`; mail catcher ready.

**Steps**
1. `wp cron event run wplalr_logs_digest_send` (or `do_action('wplalr_logs_digest_send')` via `wp eval`).
2. Check the mail catcher.

**Expected**
- One HTML email to the recipient, subject `[<site>] Daily login activity digest`,
  body listing Successful logins / Logouts / Failed logins / Total events with
  counts matching `LogRepository::stats()` over the cadence window.

## TC-N18 — Digest no-ops when cadence empty

**Steps**
1. `wp option update wplalr_logs_digest ''`.
2. `wp eval 'do_action("wplalr_logs_digest_send");'`.

**Expected**
- No email sent (the handler returns early when cadence is `''`).

## TC-N19 — Deactivate clears all cron

**Steps**
1. With a digest cadence set, deactivate the plugin:
   `wp plugin deactivate wp-login-and-logout-redirect`.
2. `wp cron event list | grep wplalr`.

**Expected**
- Both `wplalr_logs_cleanup` and `wplalr_logs_digest_send` are gone
  (`unschedule_all()` on deactivate). Reactivate to restore.

---

## Extensibility

## TC-N20 — Email payload filters re-template

**Steps**
1. Add a small mu-plugin / snippet hooking `wplalr_log_notification_email` (alert)
   and `wplalr_log_digest_email` (digest) to change the subject and/or `to`.
2. Re-run TC-N08 and TC-N17.

**Expected**
- The sent email reflects the filtered subject/recipient; returning an empty `to`
  from the filter cancels that send (no email).

---

## Mail interceptor snippet

Drop into a mu-plugin (or run via `wp eval-file`) to capture mail without SMTP. It
records the last message to an option you can read back with `wp option get`.

```php
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
    update_option( 'wplalr_test_last_mail', array(
        'to'      => $atts['to'],
        'subject' => $atts['subject'],
        'body'    => $atts['message'],
        'headers' => $atts['headers'],
    ), false );
    return true; // short-circuit actual send
}, 10, 2 );
```

```bash
# After triggering an alert/digest:
wp option get wplalr_test_last_mail --format=json
wp option delete wplalr_test_last_mail   # reset between cases
```

---

## Regression checklist (run before merge)

- [ ] `composer phpcs` → 0 errors / 0 warnings.
- [ ] `php -l` clean on `includes/Logs/Notifier.php` + the edited files.
- [ ] `npm run lint:js` (the OthersSettings card) and `npm run build` clean.
- [ ] TC-N01 … TC-N07 pass (Notifications card UI) with no console errors.
- [ ] TC-N08 … TC-N12 pass (alerts: matching/non-matching/empty/off).
- [ ] TC-N13 … TC-N19 pass (digest schedule/reschedule/clear/contents/deactivate).
- [ ] TC-N20 passes (payload filters).
