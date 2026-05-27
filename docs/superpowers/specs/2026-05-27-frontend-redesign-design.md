# MIS Inventory — Frontend Redesign Spec

**Date:** 2026-05-27
**Project:** `mis-inventory` (Laravel, MIS Office Inventory for La Union Medical Center)
**Goal:** Replace the current plain Tailwind/CDN UI with a polished bento-style design, themed in medical teal, with balanced motion across all six application pages plus auth/profile.

---

## 1. Decisions

| Decision         | Choice                                                                             |
| ---------------- | ---------------------------------------------------------------------------------- |
| Visual direction | **Bento Light** — light surfaces, varied tile sizes, soft shadows                  |
| Accent palette   | **Medical Teal** — primary `#0d9488`, accent `#06b6d4`                             |
| Motion intensity | **Balanced** — stagger entrance, count-up numbers, chart-draw, smooth hover        |
| Scope            | **All pages** — Dashboard, Receive, Release, Acknowledge, Transactions, Items + auth + profile |
| Dark mode        | **No** — light only                                                                |

---

## 2. Build & Tooling

### 2.1 Move off the Tailwind CDN

`resources/views/layouts/app.blade.php` currently loads Tailwind via `<script src="https://cdn.tailwindcss.com">`. Replace with the existing Vite pipeline:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

The CDN build cannot read `tailwind.config.js`, which blocks the custom palette, named keyframes, and `@apply` in components — so this swap is a prerequisite, not optional polish.

### 2.2 Dependencies

JS (already present in `package.json`, no new installs):
- `tailwindcss` 3.x, `@tailwindcss/forms`, `alpinejs`, `vite`, `axios`

PHP (new):
- `blade-ui-kit/blade-heroicons` — inlined Heroicons via `<x-heroicon-o-cube class="..."/>` syntax.

### 2.3 Dev workflow

- `npm run dev` — Vite HMR while iterating
- `npm run build` — production assets
- README updated with a one-line note pointing to these commands.

---

## 3. Design Tokens

All tokens live in `tailwind.config.js` so views consume them as utility classes.

### 3.1 Color

```js
// tailwind.config.js extract
colors: {
  primary: { // teal scale
    50:'#f0fdfa', 100:'#ccfbf1', 200:'#99f6e4', 300:'#5eead4',
    400:'#2dd4bf', 500:'#14b8a6', 600:'#0d9488', 700:'#0f766e',
    800:'#115e59', 900:'#134e4a',
  },
  accent:  { 500:'#06b6d4', 600:'#0891b2' }, // cyan for hero gradient stop
  surface: { page:'#f6f7fb', tile:'#ffffff', border:'#eef2f7' },
  ink:     { heading:'#0f172a', body:'#475569', muted:'#94a3b8' },
  // semantic — kept distinct from primary so they read as status, not brand
  success:'#059669', warning:'#d97706', danger:'#e11d48',
}
```

### 3.2 Typography

- Font family: **Inter** loaded from `bunny.net` (privacy-friendly, no Google call).
- Weights: 400 (body), 500 (labels), 600 (headings), 700 (numbers/emphasis).
- Heading scale pinned: `text-2xl/semibold` (h1), `text-lg/semibold` (h2), `text-sm/medium uppercase tracking-wide` (eyebrow).

### 3.3 Radii & shadow

- Tile radius: `rounded-2xl` (16px). Buttons/inputs: `rounded-lg`.
- Two shadow tokens:
  - `shadow-tile` → `0 2px 10px rgba(15,23,42,0.04)`
  - `shadow-tile-hover` → `0 14px 28px rgba(15,23,42,0.10)`

### 3.4 Motion tokens

Named in `tailwind.config.js` `theme.extend.keyframes` and `theme.extend.animation`:

| Token         | Keyframe                                          | Timing                                |
| ------------- | ------------------------------------------------- | ------------------------------------- |
| `fade-in`     | opacity 0 → 1                                     | 400ms ease-out                        |
| `slide-up`    | translateY(12px)+opacity 0 → translateY(0)+1      | 550ms cubic-bezier(.2,.7,.2,1)        |
| `pop`         | scale(.95) opacity 0 → scale(1) opacity 1         | 600ms cubic-bezier(.2,.7,.2,1)        |
| `chart-draw`  | stroke-dashoffset L → 0                           | 1400ms ease-out                       |
| `slide-in-right` | translateX(20px) opacity 0 → translateX(0) 1   | 350ms ease-out                        |
| `pop-out`     | scale(1) opacity 1 → scale(.95) opacity 0         | 250ms ease-in                         |
| `fade-out`    | opacity 1 → 0                                     | 200ms ease-in                         |

Utility delay classes: `delay-0`, `delay-100`, `delay-200`, `delay-300`, `delay-400` (multiples of 100ms).

Count-up is **not** a CSS animation. It runs in Alpine via `requestAnimationFrame`, easing 0 → target over 800ms.

**Reduced motion:** every keyframe utility is gated through a `@media (prefers-reduced-motion: no-preference)` wrapper in `app.css`. Users who opt out see content at final state instantly and count-up is skipped.

---

## 4. Component Library

All components live in `resources/views/components/` and consume the tokens above. Each is a focused, single-responsibility unit; consumers use them via Blade's `<x-foo>` syntax.

| Component             | Purpose                                                                                          |
| --------------------- | ------------------------------------------------------------------------------------------------ |
| `x-page-header`       | Page title + optional subtitle + right-slot for actions                                          |
| `x-bento-card`        | Base tile. Variants: default, `wide` (col-span), `hero` (teal→cyan gradient, white text), `accent` (subtle teal-50 tint) |
| `x-stat-tile`         | Dashboard KPI tile. Props: `label`, `value`, `icon`, optional `trend` chip. Value supports `x-count-up` |
| `x-sparkline`         | Small inline SVG line. Props: `:data` (array of numbers). Animates via `chart-draw` on `x-intersect` |
| `x-table` + `x-table.row` | Themed table: sticky header, hover row tint, no zebra                                        |
| `x-empty-state`       | Centered icon + title + hint. Used for "no transactions", "no items", etc.                       |
| `x-button`            | Variants: `primary` (teal solid), `ghost` (border only), `danger` (rose)                         |
| `x-input`, `x-select`, `x-textarea` | Themed form controls. Replaces scattered inline styles in Receive/Release forms    |
| `x-status-badge`      | Pill. Variants by status string: `pending`→rose, `acknowledged`→emerald, `released`→amber        |
| `x-toast`             | Top-right toast, slides in, auto-dismiss 4s. Replaces inline session-success banner              |

---

## 5. Page-by-Page Redesign

### 5.1 Shell — `layouts/app.blade.php` + `layouts/navigation.blade.php`

- Sidebar: icon + label per item. Active item: teal-50 pill background, primary-700 text, 2px primary-600 left bar.
- Collapsible to icons-only at `lg:` breakpoint via Alpine `x-data="{ collapsed:false }"` persisted to `localStorage`.
- Top of `<main>`: slim breadcrumb + page-header slot.
- Toast container mounted here, fixed top-right.
- The session-success / error inline divs are removed; both flow through `x-toast`.

### 5.2 Dashboard — `dashboard.blade.php`

Bento grid:

| Row | Layout                                                                        |
| --- | ----------------------------------------------------------------------------- |
| 1   | 4 `<x-stat-tile>` — Stock, Released, Pending (hero variant), Acknowledged. Stagger 0/100/200/300ms. |
| 2   | Wide `<x-bento-card hero>` "Weekly Activity" with `<x-sparkline>` (col-span-2) + `<x-bento-card>` "Top Office" (col-span-1) + `<x-bento-card>` "Top Item" (col-span-1) |
| 3   | Full-width `<x-bento-card>` containing "Pending Acknowledgments" `<x-table>`  |

Numbers count up on first paint.

**Controller change:** `DashboardController@index` currently provides `$totalInStock`, `$totalReleased`, `$pendingAck`, `$acknowledged`, `$pendingTransactions`. Add: `$weeklyActivity` (array of 7 daily release counts for the sparkline), `$topOffice` (string — office with most releases this month), `$topItem` (string — most-released item name this month).

### 5.3 Receive — `receive.blade.php`

Single-column form on a max-width `<x-bento-card>`. All inputs swap to `<x-input>` / `<x-select>`. Submit uses `<x-button variant="primary">`. On success the inline banner is gone — controller flashes `success` to session, layout renders an `<x-toast>`. Form fades+slides up on load.

### 5.4 Release — `release.blade.php`

Same form treatment as Receive, plus:
- Item-picker shows current stock count beside the chosen item, read from existing controller data — `<select>` with stock badge or live-update via Alpine on `change`.
- Confirm modal (Alpine) on submit if `qty > stock * 0.5`, to prevent accidental over-release.

### 5.5 Acknowledge — `acknowledge.blade.php`

Card list instead of a table:
- Each pending transaction is a `<x-bento-card>` row: item name, receiver, office, qty, date, Acknowledge button.
- Acknowledge button POSTs inline via Alpine `fetch` to the existing `acknowledge.update` route. On success the row plays `animate-pop-out` (250ms) before being removed from the DOM.
- Empty state via `<x-empty-state icon="check-circle" title="All caught up" hint="No pending acknowledgments."/>`.

### 5.6 Transactions — `transactions.blade.php` + `transactions-show.blade.php`

**Index:**
- Sticky filter bar at top of the content area: date range (`from`, `to`), type (Receive/Release), status (Pending/Acknowledged). Filters submit via GET to the existing controller.
- Themed `<x-table>` below. Status column uses `<x-status-badge>`.

**Controller change:** `TransactionController@index` accepts the new query params (`from`, `to`, `type`, `status`) and applies them to the existing query. Validation: dates parseable, type ∈ {receive,release}, status ∈ {pending,acknowledged}; invalid params are ignored.

**Show:**
- Two-column layout (`lg:grid-cols-3`): left `col-span-2` `<x-bento-card>` with transaction details, right `col-span-1` `<x-bento-card>` with item snapshot + ack metadata + receiver signature info.

### 5.7 Items — `items.blade.php` + `items-show.blade.php`

**Index:** card grid, `grid-cols-1 md:grid-cols-2 lg:grid-cols-3`. Each card shows item name, unit, in-stock count (large), thin `<x-sparkline>` of last-30-days movement. Hover lift on the whole card.

**Controller change:** `ItemController@index` adds a `movement30` array (length 30, daily net stock delta) to each item before passing to the view.

**Show:** header with item meta (name, unit, current stock — count-up). Below: two `<x-bento-card>` tiles side by side — "Receipts" history (themed `<x-table>`) and "Releases" history.

### 5.8 Auth + Profile — `auth/login.blade.php`, register, password reset, `profile/*`

Split layout: left half (`lg:w-1/2`) is teal→cyan gradient with the LUMC mark + a short tagline ("MIS Office Inventory"). Right half is the form on a clean `<x-bento-card>`. Form fields use the themed `<x-input>`. Page fades + slides up on load.

Profile pages: existing partials wrapped in `<x-bento-card>` with the new `<x-page-header>`.

---

## 6. Animation System

### 6.1 When each animation fires

| Trigger                           | Effect                                                                    |
| --------------------------------- | ------------------------------------------------------------------------- |
| Page-enter (any view)             | `data-anim="stagger"` container → children get `animate-slide-up` with stagger delay |
| First paint of stat tile          | `x-count-up` animates value 0 → target over 800ms                         |
| Sparkline scrolls into view       | `x-intersect` adds `animate-chart-draw` class                             |
| Tile hover                        | `hover:-translate-y-1 hover:shadow-tile-hover transition duration-200`    |
| Button press                      | `active:scale-[.98] transition`                                           |
| Toast mount                       | `animate-slide-in-right`                                                  |
| Toast dismiss                     | `animate-fade-out` then DOM removal                                       |
| Acknowledge row success           | `animate-pop-out` (250ms) then `remove()`                                 |
| Modal open                        | Backdrop fades, dialog `animate-pop`                                      |

### 6.2 Stagger plumbing

A tiny Alpine plugin (`resources/js/plugins/stagger.js`) registers a `data-anim="stagger"` MutationObserver on `x-init`: it walks direct children, applies `style.animationDelay = (index * 100) + 'ms'`, and adds `animate-slide-up`. This keeps templates free of manual `delay-0/delay-100/...` indexing.

### 6.3 Reduced motion

In `app.css`:

```css
@media (prefers-reduced-motion: reduce) {
  .animate-fade-in, .animate-slide-up, .animate-pop,
  .animate-chart-draw, .animate-slide-in-right, .animate-pop-out {
    animation: none !important;
  }
  /* count-up plugin checks matchMedia and renders final value instantly */
}
```

---

## 7. File Map

New files:
```
resources/views/components/
  page-header.blade.php
  bento-card.blade.php
  stat-tile.blade.php
  sparkline.blade.php
  table.blade.php
  table/row.blade.php
  empty-state.blade.php
  button.blade.php
  input.blade.php
  select.blade.php
  textarea.blade.php
  status-badge.blade.php
  toast.blade.php
resources/js/plugins/
  stagger.js
  count-up.js
```

Modified files:
```
tailwind.config.js                      — palette, keyframes, animation utilities
resources/css/app.css                   — Inter font, reduced-motion gate, base layer
resources/js/app.js                     — register Alpine plugins
resources/views/layouts/app.blade.php   — Vite, toast container, breadcrumb
resources/views/layouts/navigation.blade.php — sidebar with icons + collapse
resources/views/dashboard.blade.php     — bento grid
resources/views/receive.blade.php       — themed form
resources/views/release.blade.php       — themed form + confirm modal
resources/views/acknowledge.blade.php   — card list with inline ack
resources/views/transactions.blade.php  — filter bar + themed table
resources/views/transactions-show.blade.php — two-column bento
resources/views/items.blade.php         — card grid with sparklines
resources/views/items-show.blade.php    — header + two bento tiles
resources/views/auth/*.blade.php        — split layout
resources/views/profile/*.blade.php     — bento wrap
composer.json                           — add blade-ui-kit/blade-heroicons
README.md                               — one-line dev command note
.gitignore                              — already adds .superpowers/
```

Removed (replaced by components):
```
Inline session-success / @errors divs in layouts/app.blade.php
Scattered form styling in receive/release blades
```

---

## 8. Out of Scope (Deferred to Approach 3 follow-up)

- Page transitions via View Transitions API
- ⌘K command palette
- Lottie illustrations on empty states
- Chart.js dependency (replaced by inline `<x-sparkline>` SVG for now)
- Dark mode toggle

These can be layered on later without changing the component contracts above.

---

## 9. Success Criteria

The redesign is done when:

1. All views in §7 render with the new components and tokens.
2. The CDN Tailwind `<script>` tag is gone; `@vite` is the only stylesheet source.
3. `tailwind.config.js` exposes the palette, keyframes, and delay utilities described in §3.
4. Dashboard stat tiles animate in with stagger and numbers count up on first paint.
5. Sparklines draw on scroll into view.
6. Toast replaces both the success banner and the error banner in `layouts/app.blade.php`.
7. `prefers-reduced-motion: reduce` disables all keyframe animations and skips count-up.
8. `npm run build` succeeds with no Tailwind purge warnings; `npm run dev` HMRs correctly.
9. Existing controllers, routes, and database schema are unchanged.
