---
name: wplalr-admin-design
description: Design system for the WP Login and Logout Redirect React admin UI. Use when building, restyling, or reviewing any admin settings screen, card, table, button, form control, or icon in this plugin so new work matches the established WordPress-Connectors / WooCommerce-Analytics look. Triggers on tasks touching src/components/*.js, src/views/*.js, src/shared/*.js, or src/components/LayoutStyles.css.
---

# WP Login & Logout Redirect — Admin Design System

The admin app deliberately mirrors two native WordPress reference surfaces: the
**Connectors** page (`options-connectors.php`) for cards/buttons/typography, and the
**WooCommerce Analytics** tables for data tables. When in doubt, open those pages and
match their computed styles rather than inventing new values.

## Golden rules

1. **Never set `font-family`.** The whole app inherits the WP admin system font
   (`-apple-system, system-ui, "Segoe UI", Roboto, …`). No custom fonts, no monospace
   overrides — not for IPs, redirect URLs, or code chips. There must be zero
   `font-family` declarations in `LayoutStyles.css`.
2. **Use `@wordpress/components` (`Card`, `Button`, `SearchControl`, etc.), not
   hand-rolled markup.** Style them through the tokens below, don't replace them.
3. **Use the CSS custom properties, never raw hex.** All values live as `--wplalr-*`
   tokens on `.wplalr-admin-app`. Add a token before hardcoding a color/radius.
4. **Brand color is WordPress blue `#3858e9`, not purple.** Also set the Gutenberg
   accent vars so unstyled components pick up the same blue.
5. **Rebuild after every change** (`npm run build`) and verify on the live Herd site
   (`https://westore-headless.test/wp-admin/admin.php?page=wplalr_*`) — this is a
   CSS/JS bundle, source edits don't show until rebuilt.

## Design tokens (source of truth: `src/components/LayoutStyles.css`)

```css
.wplalr-admin-app {
  --wplalr-primary: #3858e9;          /* WP blue — buttons, links, active states */
  --wplalr-primary-hover: #2145e6;
  --wplalr-primary-soft: #edf1fe;     /* soft tint — pills, hovers, badges */
  --wplalr-bg: #f6f7fb;               /* app background */
  --wplalr-surface: #ffffff;          /* cards, header band */
  --wplalr-border: #dddddd;           /* card + divider border (Connectors) */
  --wplalr-border-strong: #949494;    /* input borders */
  --wplalr-text: #1e1e1e;             /* Gutenberg body text */
  --wplalr-text-muted: #757575;       /* descriptions, secondary */
  --wplalr-danger / -soft, --wplalr-success / -soft, --wplalr-warning / -soft;
  --wplalr-radius: 8px;               /* cards */
  --wplalr-radius-sm: 2px;            /* buttons, inputs (stock WP) */
  --wplalr-focus-ring: 0 0 0 3px rgba(56,88,233,.18);

  /* Make unstyled @wordpress/components use the brand blue: */
  --wp-components-color-accent: var(--wplalr-primary);
  --wp-components-color-accent-darker-10: var(--wplalr-primary-hover);
  --wp-components-color-accent-darker-20: #183ad6;
}
```

## Cards

Flat, like the Connectors page — **8px radius, `1px solid #ddd` border, no shadow.**

- Apply via `.wplalr-admin-app .components-card`; don't shadow cards.
- Section intro headers (title + description) use a transparent, borderless card.
- Section titles: `16px / 650`; descriptions: `13px`, `--wplalr-text-muted`.

## Buttons

Stock Gutenberg compact style — **`2px` radius, `13px / 500`, `text-transform: none`.**

- **Primary** (`is-primary`): solid `--wplalr-primary`, white text, no hover glow —
  just darken to `--wplalr-primary-hover`.
- **Secondary** (`is-secondary`): transparent bg, blue text, `inset 0 0 0 1px` blue
  box-shadow (WP draws the outline as a shadow, not a border). Hover → soft-blue fill.
- **Destructive actions use `variant="secondary" isDestructive`**, not solid-red
  primary. Solid red is reserved for the final confirm button inside a modal.

## Tables (`.wplalr-logs-table`, shared by Logs + Sessions)

Match WooCommerce Analytics tables:

- Table **bleeds to the card edges** — the `CardBody` wrapping a table gets the
  `wplalr-table-section-body` class (`padding: 0`, `overflow: hidden` to clip the
  header band to the card's rounded corners).
- **Header row (`th`):** `#f8f9fa` gray band, `13px / 700` sentence-case (NOT uppercase,
  no letter-spacing), `#1e1e1e`, `1px solid #e2e4e7` bottom border.
- **Body (`td`):** `13px / 400`, `--wplalr-text`, `16px 12px` padding, light
  `1px solid #f0f0f0` row borders; last row borderless. No hover tint.
- **Edge gutters:** first/last cells get `24px` left/right padding so content lines up
  with the card padding.
- **Toolbar (search + filters + actions) lives in the table's `CardHeader`**
  (`wplalr-table-toolbar`, `padding: 24px`, white, `1px #ddd` bottom border) — one
  grouped card, not a floating row above the table.
- **Bulk-action bar** renders inside the card between toolbar and table head, with
  symmetric `margin: 16px 24px` and `width: auto` (Flex defaults to 100% and overflows
  otherwise).

## Icons over text for row actions

Use `@wordpress/components` `Button` with `icon`, `label`, and `showTooltip` instead of
a text label for compact table actions. Icons come from `src/components/icons.js`
(heroicons). Give delete/logout icons the outline treatment
(`svg { fill: none; stroke: currentColor; }`).

- **Login** = `ArrowLeftEndOnRectangleIcon` (arrow entering the box).
- **Logout / end session** = `ArrowRightStartOnRectangleIcon` (arrow leaving the box),
  aliased `LogoutIcon`. Keep the login/logout pair mirrored and directionally correct —
  this was a repeated mistake; verify the glyph visually.
- **Accordion expand/collapse** = `ChevronDownIcon` / `ChevronUpIcon`. Center it with
  `vertical-align: middle` and `svg { display: block; }` to kill the baseline gap.

## Header

`SettingsHeader` (via `PageShell`) shows the **entryway logo** (`logo` prop →
`${assetsUrl}/admin/images/entryway-logo.png`, `max-height: 40px`), not an icon + title.
The plugin's `assetsUrl` is exposed through `window.wplalrAdmin` from
`includes/Assets.php`. Subtitle sits under the logo.

## Stat cards (Audit Logs)

Soft-tinted icon badges — every stat icon uses `--wplalr-stat-accent-soft` background +
accent-colored icon, all flat and equal weight. Do **not** give any single card a
gradient/glow badge (the "Total Events" card was flattened to match the others).

## Workflow checklist

1. Edit `src/**` / `LayoutStyles.css` using tokens.
2. `npm run build` (outputs to `assets/build/`).
3. Verify live at `https://westore-headless.test/wp-admin/admin.php?page=wplalr_login_logout_redirect`
   (and `…=wplalr_audit_logs`, `…=wplalr_sessions`).
4. Cross-check against the Connectors page (cards/buttons) and a WooCommerce Analytics
   table (data tables) for parity.
