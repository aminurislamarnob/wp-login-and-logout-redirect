# WP Login and Logout Redirect — Free + Pro Roadmap & Spec

Status: **Spec / planning** (no code yet). Supersedes and extends the analysis in
[issue #3](https://github.com/aminurislamarnob/wp-login-and-logout-redirect/issues/3).

## 1. Competitor analysis — verified

Issue #3 compared us with LoginWP and Sky. Verified against actual plugin source
(not just marketing copy), the claims hold. One competitor the user named was
**missing from #3: fluent-security (FluentAuth)** — added below.

| Capability | Us (today) | LoginWP free / Pro | Sky free / Pro | FluentAuth (all free) |
|---|---|---|---|---|
| Global login/logout URL | ✅ both | fallback rule / ✅ | ✅ / ✅ | default fallback |
| Rule engine (role / user / capability) | ❌ | ✅ user+role+cap / ✅ | role+user+global / + conditional | **role + capability only** (no per-user) |
| Default fallback rule | ✅ (the 2 options) | ✅ | ✅ | ✅ |
| Separate login vs logout rules | global only | ✅ | ✅ | per-rule `login` + `logout` URL |
| Placeholders `{{username}}` `{{user_slug}}` `{{website_url}}` | ❌ | ✅ free | — | ❌ |
| `{{current_page}}` / `{{previous_page}}` (referrer) | ❌ | Pro | ✅ free ("previous page") | cookie `_fls_redirect_to` (free) |
| First-login-only redirect | ❌ | Pro | — | ❌ |
| Post-registration redirect | ❌ | ✅ free | — | ❌ |
| Failed-login redirect | ❌ | ✅ (changelog) | — | ❌ |
| Loop detection / URL hardening | `wp_validate_redirect` only | `wp_validate_redirect` | ✅ loop detection | `wp_validate_redirect`, priority 999 |
| WooCommerce-aware | compat note | Pro | ✅ free / advanced Pro | partial |
| EDD-aware | ❌ | Pro | Pro | ❌ |
| Content restriction + redirect guests | ❌ | ❌ | Pro | restrict /wp-admin by role |
| Last-login tracking | ✅ **(our differentiator)** | ❌ | ❌ | audit logs (own tables) |
| Dev hooks/filters | ❌ | ✅ documented | — | ✅ many filters |

### Key takeaways from verification

- **FluentAuth is the most direct threat** — its readme literally lists
  "WP Login and Logout Redirect" as a plugin it replaces. But redirects are a
  *side feature* in a security suite (2FA, social/magic login, login-attempt
  limiting, audit logs). Its redirect rule engine matches **role + capability
  only, AND-logic within a rule, first-match-wins** (`CustomAuthHandler::getDefaultLoginRedirectUrl`).
  It has **no per-user targeting and no placeholders** — both easy wins for us.
  It's fully free, monetized via the WPManageNinja ecosystem, not a pro tier.
- **Every competitor has a rule engine; we have none.** That is the single
  biggest gap and the spine of this roadmap.
- Our **last-login column** is a genuine differentiator no competitor ships as
  core — worth extending rather than abandoning.

## 2. Product decisions (locked)

- **Delivery:** Free plugin stays on wordpress.org. **Pro is a separate add-on
  plugin distributed through Freemius** (licensing, payments, auto-updates).
- **This phase:** spec only. Build order starts with the free rule engine.

## 3. Free vs Pro feature split

### Free (this plugin)
- Ordered **rule engine**: match by **role / specific user / capability**, with
  per-rule login + logout URL; first match wins; **default fallback** = the two
  existing options (`wplalr_login_redirect`, `wplalr_logout_redirect`).
- **Free placeholders:** `{{username}}`, `{{user_slug}}`, `{{website_url}}`.
- **Loop detection** + URL validation hardening (same-site allowlist policy).
- Keep + extend **Last Login** column.
- **Extension points** (hooks/filters/JS slots) the Pro add-on plugs into.

### Pro (separate Freemius add-on)
- Dynamic placeholders `{{current_page}}` / `{{previous_page}}` (referrer).
- **First-login-only** and **post-registration** redirects.
- **WooCommerce-aware** rules (cart/checkout/My-Account) and **EDD** rules.
- **Failed-login** redirect.
- **Rule import/export** (JSON) for staging → production.
- Extended analytics: last logout, login count, CSV export.
- (Stretch epic, only if scope expands) content restriction + guest redirect.

## 4. Data model

Backward compatible — **no migration**. The two existing options become the
default fallback; a new option holds the ordered rules.

| Option key | Status | Purpose |
|---|---|---|
| `wplalr_login_redirect` | existing | Default login URL when no rule matches |
| `wplalr_logout_redirect` | existing | Default logout URL when no rule matches |
| `wplalr_redirect_rules` | **new** | Ordered array of rule objects |

Rule object shape:

```jsonc
{
  "id": "uuid",            // stable client-generated id
  "enabled": true,
  "label": "Editors → dashboard",
  "match": {
    "type": "role",        // free: "role" | "user" | "capability"
    "values": ["editor"]   // role slugs | user IDs | capability names
  },
  "login_url": "https://example.com/dashboard/",
  "logout_url": "https://example.com/bye/",

  // Pro-only fields — stored if present, ignored by free runtime:
  "first_login_only": false,
  "wc_context": false
}
```

## 5. Resolution algorithm (Redirection rewrite)

`includes/Redirection.php` keeps hooking `login_redirect`,
`woocommerce_login_redirect`, `wp_logout`, but resolves through a new
`RuleEngine`:

1. `do_action( 'wplalr/before_resolve', $event, $user )`.
2. Iterate `wplalr_redirect_rules` in order; first rule whose `match` passes for
   the current `$user` wins → take its `login_url`/`logout_url`.
3. If none match, fall back to `wplalr_login_redirect` / `wplalr_logout_redirect`.
4. If still empty, WP defaults (`admin_url()` / `home_url()`).
5. Resolve placeholders: `apply_filters( 'wplalr/placeholders', $map, $user, $ctx )`.
6. `apply_filters( 'wplalr/resolve_redirect', $url, $user, $rule, $event )`
   (Pro overrides here for WC/EDD/referrer).
7. Loop guard + `wp_validate_redirect()` against an allowlisted-host policy.

Match logic mirrors the field-tested competitor model: AND within a rule's
values is not needed (single match type per rule); `role`/`user`/`capability`
checks against `$user->roles`, `$user->ID`, and `$user->allcaps`.

## 6. REST API

Extend `GET/POST wplalr/v1/settings` (cap `manage_options`) to:

```jsonc
{
  "default_login_redirect": "…",   // maps to wplalr_login_redirect
  "default_logout_redirect": "…",  // maps to wplalr_logout_redirect
  "rules": [ /* rule objects */ ]
}
```

- Server validates each rule: `match.type` ∈ allowlist; `match.values` validated
  against `get_editable_roles()`, existing user IDs, or registered capabilities;
  URLs sanitized with `esc_url_raw`.
- Schema extensible for Pro: `apply_filters( 'wplalr/rest/rule_schema', $schema )`
  and `apply_filters( 'wplalr/rest/settings_response', $data )`.

## 7. React admin UI

- New **Rules** tab/section: ordered list of `RuleRow` cards (match-type select,
  multi-value selector, login URL, logout URL), add/remove/**drag-reorder**,
  enable toggle, inline validation, placeholder helper text.
- **Defaults / Fallback** card → the two existing options (unchanged UX for
  users who never add a rule).
- Pro fields surfaced via `wp.hooks` filters (e.g. `wplalr.ruleFields`,
  `wplalr.tabs`); when free, render a locked/upsell state.
- Replace the "Support Me" button with a Freemius-driven **Upgrade** CTA on free,
  hidden when Pro is active.

## 8. Free ⇄ Pro boundary (extension points)

PHP filters/actions the Pro plugin hooks:

- `wplalr/rule_match_types` — Pro registers advanced match types.
- `wplalr/placeholders` — Pro adds `{{current_page}}`/`{{previous_page}}`.
- `wplalr/resolve_redirect` — Pro overrides final URL (WC/EDD/referrer).
- `wplalr/before_resolve`, `wplalr/after_resolve` — lifecycle.
- `wplalr/rest/rule_schema`, `wplalr/rest/settings_response` — REST extension.

JS: `@wordpress/hooks` filters as above so Pro can inject panels/fields without
forking the free bundle.

## 9. Freemius integration (free side)

- Add Freemius SDK + `wplalr_fs()` initializer (plugin ID / public key / secret
  from the Freemius dashboard), `has_premium_version` + `is_org_compliant`.
- Free repo stays clean of paid code; Pro downloads as a separate plugin via
  Freemius. Use `wplalr_fs()->can_use_premium_code()` only inside the Pro plugin.
- Optional anonymous opt-in telemetry + upgrade/upsell surfaces.

## 10. Build order

**Free, phased:**
1. Rule-engine data model + `RuleEngine` + `Redirection` rewrite + REST changes.
2. React Rules UI (list, add/remove/reorder, validation) + Defaults card.
3. Free placeholders resolver + loop detection + URL hardening.
4. Freemius SDK (free side) + PHP/JS extension hooks scaffolding.

**Pro add-on (separate plugin, after free lands):**
5. Referrer placeholders → first-login/post-registration → WC/EDD → failed-login
   → import/export → extended analytics.

## 11. Open questions before coding phase 1

- Confirm rule match is single-type-per-rule (simpler UI) vs multi-condition AND
  (FluentAuth/LoginWP style). Recommendation: single type per rule for v1. 
  Ans: Multi-condition AND (FluentAuth/LoginWP style)
- Drag-reorder library: `@wordpress/components` has no DnD list; use
  `@dnd-kit` or simple up/down buttons for v1. Recommendation: up/down buttons v1.
  Ans: use `@dnd-kit`

## 12. Detailed phases plan

Decisions from §11 are now locked and reflected below:
- **Rule matching = multi-condition AND** (FluentAuth/LoginWP style). A rule holds
  a `conditions[]` array; **all** conditions must pass for the rule to match.
- **Drag-reorder = `@dnd-kit`** in the React UI.

This changes the rule object shape from §4 — the canonical multi-condition form is:

```jsonc
{
  "id": "uuid",
  "enabled": true,
  "label": "Editors → dashboard",
  "conditions": [                       // AND — all must match
    { "type": "role",       "values": ["editor"] },
    { "type": "capability", "values": ["edit_pages"] }
  ],
  "login_url": "https://example.com/dashboard/",
  "logout_url": "https://example.com/bye/",
  // Pro-only fields, stored if present, ignored by free runtime:
  "first_login_only": false,
  "wc_context": false
}
```

`match.type`/`match.values` references elsewhere in this doc are superseded by
`conditions[]`.

---

### Phase 1 — Rule-engine foundation (backend, no UI)

**Goal:** rules resolve at login/logout via REST-managed data; installs with no
rules behave exactly as today.

- **Data model:** new option `wplalr_redirect_rules` (ordered array of rule
  objects above). Keep `wplalr_login_redirect` / `wplalr_logout_redirect` as the
  default fallback. No migration.
- **`includes/RuleEngine.php` (new):** `resolve( $event, $user )` walks rules in
  order, returns first rule where **every** condition passes
  (`role`→`$user->roles`, `user`→`$user->ID`, `capability`→`$user->allcaps`);
  empty `conditions` = matches everyone. Fires `wplalr/before_resolve`,
  `wplalr/resolve_redirect`, `wplalr/after_resolve`.
- **`includes/Redirection.php` (rewrite):** same hooks (`login_redirect`,
  `woocommerce_login_redirect`, `wp_logout`); route through `RuleEngine` →
  default options → WP default. Retain `wp_validate_redirect()`.
- **`includes/REST/SettingsController.php` (extend):** GET/POST now read/write
  `default_login_redirect`, `default_logout_redirect`, `rules[]`. Server-side
  validation: condition `type` ∈ allowlist; `values` validated against
  `get_editable_roles()` / existing user IDs / registered capabilities; URLs via
  `esc_url_raw`. Pro-extension filters `wplalr/rest/rule_schema`,
  `wplalr/rest/settings_response`.
- **Acceptance:** rule created via REST redirects a matching user; non-matching
  users fall through to defaults; `composer phpcs` + `php -l` clean.
- **Not in scope:** UI, placeholders, Freemius.

### Phase 2 — React Rules UI

**Goal:** manage rules visually in the existing admin app.

- New **Rules** section: list of `RuleRow` cards, each with a multi-condition
  builder (add/remove condition rows: type select + multi-value selector),
  login URL, logout URL, enable toggle.
- **`@dnd-kit`** drag-to-reorder for the rule list; persists order to
  `rules[]`.
- **Defaults / Fallback** card → the two existing options (unchanged UX for
  users who add no rules).
- Inline validation, placeholder helper text. Pro fields surfaced via `wp.hooks`
  filters (`wplalr.ruleFields`, `wplalr.tabs`) with a locked/upsell state.
- **Acceptance:** create/edit/delete/reorder rules end-to-end; `npm run build`
  + `npm run lint:js` clean.

### Phase 3 — Free placeholders + hardening

- **`includes/Placeholders.php` (new):** resolver for `{{username}}`,
  `{{user_slug}}`, `{{website_url}}`, exposed via `wplalr/placeholders` filter so
  Pro can add `{{current_page}}`/`{{previous_page}}`.
- **Loop detection** + same-site allowlist URL policy in the resolve pipeline.
- **Acceptance:** placeholders expand correctly; self-referential redirects are
  caught; off-site URLs blocked unless allowlisted.

### Phase 4 — Freemius (free side) + extension scaffolding

- Freemius SDK + `wplalr_fs()` initializer (org-compliant, has-premium-version).
- Finalize PHP filters (§8) + `@wordpress/hooks` JS slots so the Pro add-on
  injects panels/fields without forking the free bundle.
- Replace "Support Me" with a Freemius **Upgrade** CTA (hidden when Pro active).
- **Acceptance:** free plugin loads with SDK, no paid code in the free repo;
  extension points documented for the Pro plugin.

### Phase 5 — Pro add-on (separate plugin, after free lands)

Built as a separate Freemius-distributed plugin hooking the Phase 4 extension
points, in this order: referrer placeholders → first-login / post-registration →
WooCommerce / EDD-aware → failed-login redirect → rule import/export → extended
analytics (last logout, login count, CSV).
