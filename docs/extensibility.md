# Extensibility — hooks for the Pro add-on

These are the seams the separate Pro plugin uses to extend the free plugin
without forking its bundle. Stable as of Phase 4.

## PHP filters & actions

### Rule resolution (`RuleEngine`)

| Hook | Type | Signature | Purpose |
|------|------|-----------|---------|
| `wplalr/before_resolve` | action | `( string $event, ?WP_User $user )` | Fires before a login/logout URL is resolved. |
| `wplalr/match_condition` | filter | `( bool $matched, string $type, array $values, WP_User $user )` | Resolve a match for a **custom** condition type the free plugin does not handle. |
| `wplalr/resolve_redirect` | filter | `( string $url, ?WP_User $user, ?array $matched, string $event )` | Override the resolved URL (e.g. referrer, WooCommerce/EDD flows). |
| `wplalr/after_resolve` | action | `( string $url, string $event, ?WP_User $user, ?array $matched )` | Fires after resolution. |

### Placeholders (`Placeholders`)

| Hook | Type | Signature | Purpose |
|------|------|-----------|---------|
| `wplalr/placeholders` | filter | `( array $map, ?WP_User $user )` | Add tokens to the `{{token}} => value` map (e.g. `{{current_page}}`, `{{previous_page}}`). |

### Redirect safety (`Redirection`)

| Hook | Type | Signature | Purpose |
|------|------|-----------|---------|
| `wplalr/allow_external_redirect` | filter | `( bool $allow, string $redirect_url )` | Return `false` to enforce a same-site-only policy on logout. |

### REST + match types (`REST\SettingsController`)

| Hook | Type | Signature | Purpose |
|------|------|-----------|---------|
| `wplalr/rule_match_types` | filter | `( array $types )` | Register custom condition match-type slugs. |
| `wplalr/sanitize_condition_values` | filter | `( array $clean, string $type, array $values )` | Sanitize values for a custom match type. |
| `wplalr/rest/rule_schema` | filter | `( array $schema )` | Extend the REST schema for a single rule. |
| `wplalr/rest/sanitize_rule` | filter | `( array $clean_rule, array $raw_rule )` | Add sanitized custom fields to a rule before it is stored. |
| `wplalr/rest/settings_response` | filter | `( array $settings )` | Add fields to the settings GET payload. |

A custom match type generally needs three hooks together: `wplalr/rule_match_types`
(register it), `wplalr/sanitize_condition_values` (validate input), and
`wplalr/match_condition` (evaluate it at runtime).

## JavaScript filters (`@wordpress/hooks`)

Registered with `wp.hooks.addFilter( name, namespace, callback )`. The admin
bundle declares `wp-hooks` as a dependency, so Pro scripts enqueued after
`wplalr-admin-page` can hook these.

| Filter | Value | Extra args | Purpose |
|--------|-------|-----------|---------|
| `wplalr.routes` | `Array<{ path, element }>` | — | Add HashRouter routes under the layout. |
| `wplalr.tabs` | `Array<{ to, icon, label }>` | — | Add navigation tabs. |
| `wplalr.headerActions` | React element | — | Replace/augment the header action buttons (e.g. an Upgrade CTA). |
| `wplalr.conditionTypes` | `Array<{ label, value }>` | — | Add match-type options to the condition selector. |
| `wplalr.conditionValueControl` | React element \| null | `{ type, values, onChange }` | Render the value control for a custom match type (return `null` to fall back). |
| `wplalr.ruleFields` | React element \| null | `{ rule, onChange }` | Inject extra fields into each rule card (e.g. first-login-only, WooCommerce context). |

## Data model note

The free REST sanitizer keeps a fixed key set per rule, so optional Pro fields
(`first_login_only`, `wc_context`, …) must be: declared via `wplalr/rest/rule_schema`,
persisted via `wplalr/rest/sanitize_rule`, surfaced in the UI via `wplalr.ruleFields`,
and acted on at runtime via `wplalr/resolve_redirect`. The free runtime ignores any
field it does not recognize.
