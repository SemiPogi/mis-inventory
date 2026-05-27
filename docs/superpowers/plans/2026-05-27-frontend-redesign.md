# MIS Inventory — Frontend Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the current plain Tailwind/CDN UI with a polished bento-style design, themed in medical teal, with balanced motion across all pages plus auth/profile.

**Architecture:** Switch from CDN Tailwind to the project's existing Vite pipeline so we can extend `tailwind.config.js`. Build a small Blade component library (`x-bento-card`, `x-stat-tile`, `x-sparkline`, `x-table`, `x-toast`, themed form controls) that all views consume. Animations live as named Tailwind keyframes (`fade-in`, `slide-up`, `pop`, `chart-draw`) plus two small Alpine plugins (`x-count-up`, `data-anim="stagger"`). Reduced-motion is honored globally.

**Tech Stack:** Laravel 13 + Blade, Tailwind CSS 3 (via Vite), Alpine.js 3, Heroicons (via `blade-ui-kit/blade-heroicons`), Inter font from bunny.net. No new JS bundler deps; no new frontend frameworks.

**Spec:** [docs/superpowers/specs/2026-05-27-frontend-redesign-design.md](../specs/2026-05-27-frontend-redesign-design.md)

---

## Conventions for every task

- Run `npm run dev` in one terminal once you start Task 1 — it gives you HMR for the whole plan.
- After any blade or config change, refresh the browser to confirm.
- Pest feature tests are added where logic changes (controllers, new data). Pure presentation changes are verified manually in the browser.
- Commit at the end of each task.

---

## Phase 1 — Foundation

### Task 1: Swap Tailwind CDN for Vite + load Inter

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (head only — keep the rest untouched for now)
- Modify: `resources/css/app.css`

- [ ] **Step 1: Update the `<head>` of the main layout**

Replace lines 1–8 of `resources/views/layouts/app.blade.php` with:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MIS Inventory — LUMC</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
```

Leave the `<body>` and everything below it intact — Task 11 rewrites those.

- [ ] **Step 2: Update `resources/css/app.css` to add the font + reduced-motion gate**

Replace the file contents with:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
    html { font-family: 'Inter', system-ui, sans-serif; }
    body { @apply bg-surface-page text-ink-body antialiased; }
}

@media (prefers-reduced-motion: reduce) {
    .animate-fade-in,
    .animate-slide-up,
    .animate-pop,
    .animate-chart-draw,
    .animate-slide-in-right,
    .animate-pop-out,
    .animate-fade-out { animation: none !important; }
}
```

Note: `bg-surface-page` and `text-ink-body` come from the Tailwind config in Task 2 — they'll fail until Task 2 lands. That's fine; Task 2 is the next commit.

- [ ] **Step 3: Start the dev server (keep running for the rest of the plan)**

Run in a separate terminal:

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
npm run dev
```

Expected: Vite starts on port 5173, watches files.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/css/app.css
git commit -m "build: swap Tailwind CDN for Vite + add Inter font"
```

---

### Task 2: Configure Tailwind palette, keyframes, delay utilities

**Files:**
- Modify: `tailwind.config.js` (full replacement)

- [ ] **Step 1: Replace the contents of `tailwind.config.js`**

```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    safelist: [
        'delay-0', 'delay-100', 'delay-200', 'delay-300', 'delay-400',
        'animate-slide-up', 'animate-fade-in', 'animate-pop',
        'animate-chart-draw', 'animate-slide-in-right',
        'animate-pop-out', 'animate-fade-out',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50:  '#f0fdfa',
                    100: '#ccfbf1',
                    200: '#99f6e4',
                    300: '#5eead4',
                    400: '#2dd4bf',
                    500: '#14b8a6',
                    600: '#0d9488',
                    700: '#0f766e',
                    800: '#115e59',
                    900: '#134e4a',
                },
                accent: {
                    500: '#06b6d4',
                    600: '#0891b2',
                },
                surface: {
                    page:   '#f6f7fb',
                    tile:   '#ffffff',
                    border: '#eef2f7',
                },
                ink: {
                    heading: '#0f172a',
                    body:    '#475569',
                    muted:   '#94a3b8',
                },
                success: '#059669',
                warning: '#d97706',
                danger:  '#e11d48',
            },
            boxShadow: {
                tile:        '0 2px 10px rgba(15, 23, 42, 0.04)',
                'tile-hover': '0 14px 28px rgba(15, 23, 42, 0.10)',
            },
            transitionDelay: {
                0:   '0ms',
                100: '100ms',
                200: '200ms',
                300: '300ms',
                400: '400ms',
            },
            keyframes: {
                'fade-in':         { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                'slide-up':        { '0%': { transform: 'translateY(12px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                'pop':             { '0%': { transform: 'scale(.95)', opacity: '0' }, '100%': { transform: 'scale(1)', opacity: '1' } },
                'chart-draw':      { '0%': { strokeDashoffset: 'var(--dash-len, 600)' }, '100%': { strokeDashoffset: '0' } },
                'slide-in-right':  { '0%': { transform: 'translateX(20px)', opacity: '0' }, '100%': { transform: 'translateX(0)', opacity: '1' } },
                'pop-out':         { '0%': { transform: 'scale(1)', opacity: '1' }, '100%': { transform: 'scale(.95)', opacity: '0' } },
                'fade-out':        { '0%': { opacity: '1' }, '100%': { opacity: '0' } },
            },
            animation: {
                'fade-in':        'fade-in 400ms ease-out forwards',
                'slide-up':       'slide-up 550ms cubic-bezier(.2,.7,.2,1) forwards',
                'pop':            'pop 600ms cubic-bezier(.2,.7,.2,1) forwards',
                'chart-draw':     'chart-draw 1400ms ease-out forwards',
                'slide-in-right': 'slide-in-right 350ms ease-out forwards',
                'pop-out':        'pop-out 250ms ease-in forwards',
                'fade-out':       'fade-out 200ms ease-in forwards',
            },
        },
    },

    plugins: [
        forms,
        // animation-delay utilities (.delay-0 ... .delay-400)
        function ({ addUtilities }) {
            addUtilities({
                '.delay-0':   { 'animation-delay': '0ms' },
                '.delay-100': { 'animation-delay': '100ms' },
                '.delay-200': { 'animation-delay': '200ms' },
                '.delay-300': { 'animation-delay': '300ms' },
                '.delay-400': { 'animation-delay': '400ms' },
            });
        },
    ],
};
```

- [ ] **Step 2: Verify the dev server picks it up**

Watch the Vite terminal — it should recompile without errors. Reload any page in the browser; the CSS classes from `app.css` (`bg-surface-page`, `text-ink-body`) should now resolve. The page background should be `#f6f7fb` (instead of the previous `bg-gray-100`) once Task 11 wires the body class — for now, the change is invisible on existing pages but the utilities are available.

- [ ] **Step 3: Sanity-check a custom utility resolves**

Open a Blade file in your browser (any page works since you're logged in), open DevTools → Console, run:

```js
getComputedStyle(document.body).getPropertyValue('font-family')
```

Expected: starts with `Inter`. If not, hard-refresh.

- [ ] **Step 4: Commit**

```bash
git add tailwind.config.js
git commit -m "build: extend Tailwind with medical-teal palette + motion tokens"
```

---

### Task 3: Install blade-heroicons

**Files:**
- Modify: `composer.json` (via composer require — let composer write)

- [ ] **Step 1: Install the package**

Run:

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
composer require blade-ui-kit/blade-heroicons
```

Expected: package installed, `composer.json` updated, `php artisan package:discover` runs successfully.

- [ ] **Step 2: Smoke-test in a Blade view**

Open `resources/views/dashboard.blade.php`, temporarily add at the very top (inside `<x-app-layout>`):

```blade
<x-heroicon-o-cube class="w-6 h-6 text-primary-600"/>
```

Reload `/` in browser. Expected: a teal cube icon renders. Remove the line after verifying.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "build: add blade-heroicons for inlined SVG icons"
```

---

### Task 4: Add Alpine plugins (count-up, stagger, intersect)

**Files:**
- Create: `resources/js/plugins/count-up.js`
- Create: `resources/js/plugins/stagger.js`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Create `resources/js/plugins/count-up.js`**

```js
// x-count-up: animates the element's numeric text from 0 to its current value
// over `duration` ms. Skips animation if prefers-reduced-motion is set.
export default function (Alpine) {
    Alpine.directive('count-up', (el, { expression }, { evaluate }) => {
        const target = parseFloat(el.textContent.replace(/,/g, '')) || 0;
        const duration = expression ? evaluate(expression) : 800;

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            el.textContent = target.toLocaleString();
            return;
        }

        const start = performance.now();
        const ease = (t) => 1 - Math.pow(1 - t, 3); // ease-out-cubic

        const tick = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const value = Math.round(target * ease(progress));
            el.textContent = value.toLocaleString();
            if (progress < 1) requestAnimationFrame(tick);
        };

        el.textContent = '0';
        requestAnimationFrame(tick);
    });
}
```

- [ ] **Step 2: Create `resources/js/plugins/stagger.js`**

```js
// data-anim="stagger" container: assigns animation-delay to direct children
// in 100ms increments and adds .animate-slide-up. Wire on x-init.
//
// Usage:  <div x-data x-init="$stagger($el)" data-anim="stagger"> ... </div>
export default function (Alpine) {
    Alpine.magic('stagger', () => (el) => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const children = Array.from(el.children);
        children.forEach((child, i) => {
            child.style.animationDelay = `${i * 100}ms`;
            child.classList.add('animate-slide-up');
        });
    });
}
```

- [ ] **Step 3: Replace `resources/js/app.js`**

```js
import './bootstrap';
import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
import countUp from './plugins/count-up';
import stagger from './plugins/stagger';

Alpine.plugin(intersect);
Alpine.plugin(countUp);
Alpine.plugin(stagger);

window.Alpine = Alpine;
Alpine.start();
```

- [ ] **Step 4: Install `@alpinejs/intersect`**

```bash
npm install @alpinejs/intersect
```

Expected: package installed, `package.json` updated.

- [ ] **Step 5: Restart Vite if it's open**

In the dev-server terminal: Ctrl-C then `npm run dev` again. Expected: clean reload, no errors about missing modules.

- [ ] **Step 6: Smoke-test in browser**

Open DevTools → Console on any page, type:

```js
Alpine.version
```

Expected: a version string like `3.x.x`. No errors in console.

- [ ] **Step 7: Commit**

```bash
git add resources/js package.json package-lock.json
git commit -m "build: add Alpine plugins — count-up, stagger, intersect"
```

---

## Phase 2 — Component library

### Task 5: Form primitives — x-button, x-input, x-select, x-textarea, x-label

**Files:**
- Create: `resources/views/components/button.blade.php`
- Create: `resources/views/components/input.blade.php`
- Create: `resources/views/components/select.blade.php`
- Create: `resources/views/components/textarea.blade.php`
- Create: `resources/views/components/label.blade.php`

- [ ] **Step 1: Create `resources/views/components/button.blade.php`**

```blade
@props([
    'variant' => 'primary', // primary | ghost | danger
    'type' => 'button',
    'as' => 'button',       // 'button' or 'a'
])

@php
    $base = 'inline-flex items-center justify-center gap-2 text-sm font-medium px-5 py-2 rounded-lg transition active:scale-[.98] focus:outline-none focus:ring-2 focus:ring-offset-2';
    $variants = [
        'primary' => 'bg-primary-600 hover:bg-primary-700 text-white focus:ring-primary-500',
        'ghost'   => 'border border-surface-border bg-white text-ink-body hover:bg-surface-page focus:ring-primary-500',
        'danger'  => 'bg-danger hover:bg-rose-700 text-white focus:ring-rose-500',
    ];
    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if($as === 'a')
    <a {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
```

- [ ] **Step 2: Create `resources/views/components/input.blade.php`**

```blade
@props(['type' => 'text'])

<input type="{{ $type }}"
    {{ $attributes->class('w-full border border-surface-border bg-white rounded-lg px-3 py-2 text-sm text-ink-heading placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition') }}>
```

- [ ] **Step 3: Create `resources/views/components/select.blade.php`**

```blade
<select
    {{ $attributes->class('w-full border border-surface-border bg-white rounded-lg px-3 py-2 text-sm text-ink-heading focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition') }}>
    {{ $slot }}
</select>
```

- [ ] **Step 4: Create `resources/views/components/textarea.blade.php`**

```blade
@props(['rows' => 3])

<textarea rows="{{ $rows }}"
    {{ $attributes->class('w-full border border-surface-border bg-white rounded-lg px-3 py-2 text-sm text-ink-heading placeholder:text-ink-muted focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition') }}>{{ $slot }}</textarea>
```

- [ ] **Step 5: Create `resources/views/components/label.blade.php`**

```blade
@props(['for' => null, 'required' => false])

<label @if($for) for="{{ $for }}" @endif
    class="block text-xs font-medium text-ink-muted uppercase tracking-wide mb-1.5">
    {{ $slot }}@if($required)<span class="text-danger ml-0.5">*</span>@endif
</label>
```

- [ ] **Step 6: Smoke-test in the browser**

Temporarily edit `resources/views/dashboard.blade.php`, add at the top inside `<x-app-layout>`:

```blade
<div class="space-x-3 mb-4">
    <x-button>Primary</x-button>
    <x-button variant="ghost">Ghost</x-button>
    <x-button variant="danger">Danger</x-button>
</div>
<x-label required>Item name</x-label>
<x-input placeholder="e.g. Bond paper"/>
```

Reload `/`. Expected: three styled buttons, a label, a teal-focused input. Remove the test block after.

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/button.blade.php resources/views/components/input.blade.php resources/views/components/select.blade.php resources/views/components/textarea.blade.php resources/views/components/label.blade.php
git commit -m "feat(ui): add form primitive components"
```

---

### Task 6: Layout primitives — x-bento-card, x-page-header

**Files:**
- Create: `resources/views/components/bento-card.blade.php`
- Create: `resources/views/components/page-header.blade.php`

- [ ] **Step 1: Create `resources/views/components/bento-card.blade.php`**

```blade
@props([
    'variant' => 'default', // default | hero | accent
    'padded'  => true,
    'hover'   => false,
])

@php
    $base = 'rounded-2xl shadow-tile transition';
    $variants = [
        'default' => 'bg-surface-tile',
        'accent'  => 'bg-primary-50',
        'hero'    => 'bg-gradient-to-br from-primary-600 to-accent-500 text-white',
    ];
    $hoverClasses = $hover ? 'hover:-translate-y-1 hover:shadow-tile-hover' : '';
    $padding = $padded ? 'p-5' : '';
    $classes = trim($base . ' ' . ($variants[$variant] ?? $variants['default']) . ' ' . $padding . ' ' . $hoverClasses);
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>
```

- [ ] **Step 2: Create `resources/views/components/page-header.blade.php`**

```blade
@props(['title', 'subtitle' => null])

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-ink-heading">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-sm text-ink-muted mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="flex gap-2">{{ $actions }}</div>
    @endif
</div>
```

- [ ] **Step 3: Smoke-test**

Temporarily replace the top of `resources/views/dashboard.blade.php`:

```blade
<x-app-layout>
    <x-page-header title="Dashboard" subtitle="MIS Office Inventory Overview">
        <x-slot:actions>
            <x-button variant="ghost">Export</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-bento-card variant="hero">
        <p class="text-xs uppercase tracking-wide opacity-80">Hero card</p>
        <p class="text-2xl font-bold">128</p>
    </x-bento-card>
```

(Don't commit the smoke-test edit — revert after eyeballing.)

Reload `/`. Expected: header on top, teal→cyan gradient hero card below it.

- [ ] **Step 4: Revert dashboard.blade.php to its previous state, then commit the components**

```bash
git checkout resources/views/dashboard.blade.php
git add resources/views/components/bento-card.blade.php resources/views/components/page-header.blade.php
git commit -m "feat(ui): add bento-card + page-header components"
```

---

### Task 7: x-status-badge, x-empty-state

**Files:**
- Create: `resources/views/components/status-badge.blade.php`
- Create: `resources/views/components/empty-state.blade.php`

- [ ] **Step 1: Create `resources/views/components/status-badge.blade.php`**

```blade
@props(['status' => 'neutral']) {{-- pending | acknowledged | received | released | neutral --}}

@php
    $map = [
        'pending'      => ['bg' => 'bg-rose-50',    'text' => 'text-rose-700',    'label' => 'Pending'],
        'acknowledged' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'label' => 'Acknowledged'],
        'received'     => ['bg' => 'bg-primary-50', 'text' => 'text-primary-700', 'label' => 'Received'],
        'released'     => ['bg' => 'bg-amber-50',   'text' => 'text-amber-700',   'label' => 'Released'],
        'neutral'      => ['bg' => 'bg-surface-page', 'text' => 'text-ink-body',  'label' => ''],
    ];
    $s = $map[$status] ?? $map['neutral'];
@endphp

<span {{ $attributes->class("inline-flex items-center {$s['bg']} {$s['text']} text-xs font-medium px-2.5 py-1 rounded-full") }}>
    {{ trim($slot) !== '' ? $slot : $s['label'] }}
</span>
```

- [ ] **Step 2: Create `resources/views/components/empty-state.blade.php`**

```blade
@props([
    'icon' => 'inbox',      {{-- heroicon name (outline variant) --}}
    'title' => 'Nothing here yet',
    'hint' => null,
])

<div class="flex flex-col items-center justify-center text-center py-12 px-6">
    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-10 h-10 text-ink-muted mb-3"/>
    <p class="text-sm font-medium text-ink-heading">{{ $title }}</p>
    @if($hint)
        <p class="text-xs text-ink-muted mt-1">{{ $hint }}</p>
    @endif
    @if(isset($action))
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>
```

- [ ] **Step 3: Smoke-test**

Temporary edit to `dashboard.blade.php`:

```blade
<div class="space-x-2 my-4">
    <x-status-badge status="pending"/>
    <x-status-badge status="acknowledged"/>
    <x-status-badge status="received"/>
    <x-status-badge status="released"/>
</div>
<x-bento-card>
    <x-empty-state icon="check-circle" title="All caught up" hint="No pending acknowledgments." />
</x-bento-card>
```

Reload. Expected: 4 colored pills, then a card containing a centered check-circle icon + title + hint. Revert.

- [ ] **Step 4: Commit**

```bash
git checkout resources/views/dashboard.blade.php
git add resources/views/components/status-badge.blade.php resources/views/components/empty-state.blade.php
git commit -m "feat(ui): add status-badge + empty-state components"
```

---

### Task 8: x-table + x-table.row

**Files:**
- Create: `resources/views/components/table.blade.php`
- Create: `resources/views/components/table/row.blade.php`

- [ ] **Step 1: Create `resources/views/components/table.blade.php`**

```blade
@props(['headers' => []])

<div {{ $attributes->class('overflow-x-auto rounded-2xl bg-surface-tile shadow-tile') }}>
    <table class="w-full text-sm">
        @if(count($headers) > 0)
        <thead class="bg-surface-page/50">
            <tr class="border-b border-surface-border">
                @foreach($headers as $h)
                    <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
```

- [ ] **Step 2: Create `resources/views/components/table/row.blade.php`**

```blade
<tr {{ $attributes->class('border-b border-surface-border/60 last:border-b-0 hover:bg-surface-page/60 transition') }}>
    {{ $slot }}
</tr>
```

- [ ] **Step 3: Smoke-test**

Temporary edit to `dashboard.blade.php`:

```blade
<x-table :headers="['Item', 'Qty', 'Status']">
    <x-table.row>
        <td class="px-6 py-3 font-medium text-ink-heading">Bond Paper</td>
        <td class="px-6 py-3 text-ink-body">12 reams</td>
        <td class="px-6 py-3"><x-status-badge status="pending"/></td>
    </x-table.row>
    <x-table.row>
        <td class="px-6 py-3 font-medium text-ink-heading">USB-C Cable</td>
        <td class="px-6 py-3 text-ink-body">3 pcs</td>
        <td class="px-6 py-3"><x-status-badge status="acknowledged"/></td>
    </x-table.row>
</x-table>
```

Reload `/`. Expected: clean themed table with hover rows. Revert.

- [ ] **Step 4: Commit**

```bash
git checkout resources/views/dashboard.blade.php
git add resources/views/components/table.blade.php resources/views/components/table/row.blade.php
git commit -m "feat(ui): add themed table components"
```

---

### Task 9: x-toast (component + container)

**Files:**
- Create: `resources/views/components/toast.blade.php`
- Create: `resources/views/components/toast-container.blade.php`

- [ ] **Step 1: Create `resources/views/components/toast.blade.php`**

```blade
@props([
    'type' => 'success', // success | error | info
])

@php
    $map = [
        'success' => ['icon' => 'check-circle', 'bg' => 'bg-emerald-50',  'text' => 'text-emerald-800', 'iconColor' => 'text-emerald-600'],
        'error'   => ['icon' => 'x-circle',     'bg' => 'bg-rose-50',     'text' => 'text-rose-800',    'iconColor' => 'text-rose-600'],
        'info'    => ['icon' => 'information-circle', 'bg' => 'bg-primary-50', 'text' => 'text-primary-800', 'iconColor' => 'text-primary-600'],
    ];
    $t = $map[$type] ?? $map['success'];
@endphp

<div x-data="{ shown: true }"
     x-show="shown"
     x-init="setTimeout(() => shown = false, 4000)"
     x-transition:enter="animate-slide-in-right"
     x-transition:leave="animate-fade-out"
     class="flex items-start gap-3 {{ $t['bg'] }} {{ $t['text'] }} rounded-xl shadow-tile px-4 py-3 max-w-sm pointer-events-auto">
    <x-dynamic-component :component="'heroicon-o-' . $t['icon']" class="w-5 h-5 {{ $t['iconColor'] }} shrink-0 mt-0.5"/>
    <div class="text-sm flex-1">{{ $slot }}</div>
    <button @click="shown = false" class="{{ $t['iconColor'] }} hover:opacity-70">
        <x-heroicon-o-x-mark class="w-4 h-4"/>
    </button>
</div>
```

- [ ] **Step 2: Create `resources/views/components/toast-container.blade.php`**

```blade
<div class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
    @if(session('success'))
        <x-toast type="success">{{ session('success') }}</x-toast>
    @endif
    @if(session('error'))
        <x-toast type="error">{{ session('error') }}</x-toast>
    @endif
    @if($errors->any())
        <x-toast type="error">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-toast>
    @endif
</div>
```

(This container is mounted in the layout in Task 11.)

- [ ] **Step 3: Smoke-test**

Temporarily add to `dashboard.blade.php` (top of `<x-app-layout>`):

```blade
<x-toast type="success">Item recorded successfully.</x-toast>
```

Reload `/`. Expected: a green slide-in toast that auto-dismisses after 4s. Revert.

- [ ] **Step 4: Commit**

```bash
git checkout resources/views/dashboard.blade.php
git add resources/views/components/toast.blade.php resources/views/components/toast-container.blade.php
git commit -m "feat(ui): add toast + toast-container components"
```

---

### Task 10: Data primitives — x-stat-tile, x-sparkline

**Files:**
- Create: `resources/views/components/stat-tile.blade.php`
- Create: `resources/views/components/sparkline.blade.php`

- [ ] **Step 1: Create `resources/views/components/stat-tile.blade.php`**

```blade
@props([
    'label' => '',
    'value' => 0,
    'icon' => null,
    'variant' => 'default', // default | hero
    'animate' => true,
])

@php
    $bg = $variant === 'hero'
        ? 'bg-gradient-to-br from-primary-600 to-accent-500 text-white'
        : 'bg-surface-tile';
    $labelColor = $variant === 'hero' ? 'text-white/80' : 'text-ink-muted';
    $valueColor = $variant === 'hero' ? 'text-white' : 'text-ink-heading';
    $iconColor  = $variant === 'hero' ? 'text-white/80' : 'text-primary-600';
@endphp

<div {{ $attributes->class("$bg rounded-2xl shadow-tile p-5 transition hover:-translate-y-1 hover:shadow-tile-hover") }}>
    <div class="flex items-start justify-between">
        <p class="text-xs {{ $labelColor }} uppercase tracking-wide font-medium">{{ $label }}</p>
        @if($icon)
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5 {{ $iconColor }}"/>
        @endif
    </div>
    <p class="text-3xl font-bold {{ $valueColor }} mt-2" @if($animate) x-data x-count-up @endif>{{ $value }}</p>
    @if(isset($trend))
        <div class="mt-2 text-xs">{{ $trend }}</div>
    @endif
</div>
```

- [ ] **Step 2: Create `resources/views/components/sparkline.blade.php`**

```blade
@props([
    'data' => [],
    'color' => '#0d9488',
    'height' => 40,
    'width' => 120,
])

@php
    $values = array_values(array_map('floatval', $data ?: [0]));
    $max = max($values) ?: 1;
    $min = min($values);
    $range = ($max - $min) ?: 1;
    $count = count($values);
    $stepX = $count > 1 ? $width / ($count - 1) : 0;

    $points = [];
    foreach ($values as $i => $v) {
        $x = round($i * $stepX, 2);
        $y = round($height - (($v - $min) / $range) * $height, 2);
        $points[] = "{$x},{$y}";
    }
    $path = 'M ' . implode(' L ', $points);
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}"
     preserveAspectRatio="none"
     x-data x-intersect.once="$el.querySelector('path').classList.add('animate-chart-draw')"
     {{ $attributes->class('block w-full h-full') }}>
    <path d="{{ $path }}"
          fill="none"
          stroke="{{ $color }}"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          style="--dash-len: 600; stroke-dasharray: 600; stroke-dashoffset: 600;"/>
</svg>
```

- [ ] **Step 3: Smoke-test**

Temporarily replace `dashboard.blade.php`'s content with:

```blade
<x-app-layout>
    <div class="grid grid-cols-4 gap-4 mb-6" x-data x-init="$stagger($el)" data-anim="stagger">
        <x-stat-tile label="In Stock" value="128" icon="cube"/>
        <x-stat-tile label="Released" value="47" icon="paper-airplane"/>
        <x-stat-tile label="Pending"  value="12" variant="hero" icon="clock"/>
        <x-stat-tile label="Acknowledged" value="35" icon="check-badge"/>
    </div>
    <x-bento-card>
        <p class="text-sm font-medium text-ink-heading mb-3">Activity sparkline</p>
        <div class="h-20">
            <x-sparkline :data="[3, 7, 4, 9, 6, 12, 8, 14, 11, 16, 12, 18]"/>
        </div>
    </x-bento-card>
</x-app-layout>
```

Reload `/`. Expected: 4 tiles stagger in, numbers count up, "Pending" tile uses the teal→cyan gradient, sparkline draws on first paint. Revert with `git checkout`.

- [ ] **Step 4: Commit**

```bash
git checkout resources/views/dashboard.blade.php
git add resources/views/components/stat-tile.blade.php resources/views/components/sparkline.blade.php
git commit -m "feat(ui): add stat-tile + sparkline components"
```

---

## Phase 3 — Application shell

### Task 11: Rewrite `layouts/app.blade.php` (sidebar, breadcrumb slot, toast container)

**Files:**
- Modify: `resources/views/layouts/app.blade.php` (full replacement)

- [ ] **Step 1: Replace the entire contents of `resources/views/layouts/app.blade.php`**

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MIS Inventory — LUMC</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-page text-ink-body antialiased">

@php
    $nav = [
        ['route' => 'dashboard',          'label' => 'Dashboard',    'icon' => 'home',           'match' => '/'],
        ['route' => 'receive.index',      'label' => 'Receive',      'icon' => 'arrow-down-tray', 'match' => 'receive'],
        ['route' => 'release.index',      'label' => 'Release',      'icon' => 'arrow-up-tray',   'match' => 'release'],
        ['route' => 'acknowledge.index',  'label' => 'Acknowledge',  'icon' => 'check-circle',    'match' => 'acknowledge'],
        ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'clipboard-document-list', 'match' => 'transactions*'],
        ['route' => 'items.index',        'label' => 'Inventory',    'icon' => 'cube',            'match' => 'items*'],
    ];
@endphp

<div class="flex min-h-screen" x-data="{ collapsed: localStorage.getItem('sidebar-collapsed') === '1' }">

    {{-- Sidebar --}}
    <aside :class="collapsed ? 'w-20' : 'w-64'"
           class="bg-surface-tile border-r border-surface-border flex flex-col transition-all duration-200">

        <div class="px-5 py-5 border-b border-surface-border flex items-center justify-between">
            <div x-show="!collapsed" x-transition.opacity>
                <p class="text-sm font-semibold text-primary-700 uppercase tracking-wide">MIS Office</p>
                <p class="text-xs text-ink-muted mt-0.5">La Union Medical Center</p>
            </div>
            <button @click="collapsed = !collapsed; localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0')"
                    class="text-ink-muted hover:text-primary-600 transition" title="Toggle sidebar">
                <x-heroicon-o-bars-3 class="w-5 h-5"/>
            </button>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1">
            @foreach($nav as $item)
                @php $active = request()->is($item['match']); @endphp
                <a href="{{ route($item['route']) }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $active
                                ? 'bg-primary-50 text-primary-700 font-medium'
                                : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
                    @if($active)
                        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
                    @endif
                    <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="w-5 h-5 shrink-0"/>
                    <span x-show="!collapsed" x-transition.opacity>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="px-5 py-4 border-t border-surface-border" x-show="!collapsed" x-transition.opacity>
            <p class="text-sm font-medium text-ink-heading truncate">{{ auth()->user()->name }}</p>
            <p class="text-xs text-ink-muted truncate">{{ auth()->user()->email }}</p>
            <div class="mt-2 flex gap-3 text-xs">
                <a href="{{ route('profile.edit') }}" class="text-ink-muted hover:text-primary-600">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-danger hover:text-rose-700">Logout</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <main class="flex-1 p-8 overflow-auto">
        {{ $slot }}
    </main>
</div>

<x-toast-container />

</body>
</html>
```

- [ ] **Step 2: Reload `/` in browser**

Expected:
- Sidebar with icons + labels
- Active nav item gets a teal pill background + a 2px left bar
- Hamburger button collapses the sidebar to icons-only; collapsed state persists across reloads (localStorage)
- Page background is `#f6f7fb` (surface-page)
- Existing dashboard markup still renders (we haven't touched dashboard yet)

- [ ] **Step 3: Verify toast container works**

Add a flash message by visiting any form that flashes `success` — e.g. submit the existing Receive form once. Expected: a green toast slides in top-right and auto-dismisses after 4s. The old inline green banner from session('success') no longer appears (it was removed).

- [ ] **Step 4: Write a Pest feature test for the layout**

Create `tests/Feature/LayoutTest.php`:

```php
<?php

use App\Models\User;

test('authenticated user sees the new sidebar with primary-themed active state', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('MIS Office', false)
        ->assertSee('La Union Medical Center', false)
        ->assertSee('href="' . route('receive.index') . '"', false)
        ->assertSee('bg-primary-50', false); // active dashboard pill
});
```

- [ ] **Step 5: Run the test**

```bash
php artisan test --filter=LayoutTest
```

Expected: 1 passing test.

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/app.blade.php tests/Feature/LayoutTest.php
git commit -m "feat(ui): rewrite app layout with teal sidebar, breadcrumb slot, toast container"
```

---

## Phase 4 — Page redesigns

### Task 12: Dashboard — controller data + bento grid

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php` (full replacement)
- Create: `tests/Feature/DashboardTest.php`

- [ ] **Step 1: Write a failing feature test first**

Create `tests/Feature/DashboardTest.php`:

```php
<?php

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;

test('dashboard provides weeklyActivity, topOffice, topItem to the view', function () {
    $user = User::factory()->create();

    // Seed a release this month to make topOffice/topItem deterministic
    $item = Item::create([
        'name' => 'Bond Paper', 'unit' => 'reams',
        'current_qty' => 50, 'total_qty_received' => 50,
    ]);
    Transaction::create([
        'item_id' => $item->id,
        'type' => 'released',
        'item_name_snapshot' => 'Bond Paper',
        'qty' => 3, 'unit' => 'reams',
        'receiver_name' => 'Maria Santos',
        'released_to_office' => 'Radiology',
        'date_released' => now()->toDateString(),
        'acknowledgment_status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertViewHas('weeklyActivity')
        ->assertViewHas('topOffice', 'Radiology')
        ->assertViewHas('topItem', 'Bond Paper');

    $weekly = $response->viewData('weeklyActivity');
    expect($weekly)->toBeArray()->toHaveCount(7);
});
```

- [ ] **Step 2: Run the test — expect failure**

```bash
php artisan test --filter=DashboardTest
```

Expected: FAIL — view does not have `weeklyActivity`, `topOffice`, or `topItem`.

- [ ] **Step 3: Replace `app/Http/Controllers/DashboardController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $totalInStock = Item::where('current_qty', '>', 0)->count();
        $totalReleased = Transaction::where('type', 'released')->count();
        $pendingAck = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')->count();
        $acknowledged = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'acknowledged')->count();

        $pendingTransactions = Transaction::where('type', 'released')
            ->where('acknowledgment_status', 'pending')
            ->latest()
            ->limit(8)
            ->get();

        // 7-day release activity (today inclusive)
        $weeklyActivity = collect(range(6, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            return Transaction::where('type', 'released')
                ->whereDate('date_released', $date)
                ->count();
        })->all();

        // Top office + item this calendar month (by release count)
        $startOfMonth = Carbon::now()->startOfMonth();
        $topOffice = Transaction::where('type', 'released')
            ->where('date_released', '>=', $startOfMonth)
            ->selectRaw('released_to_office, COUNT(*) as c')
            ->groupBy('released_to_office')
            ->orderByDesc('c')
            ->value('released_to_office');

        $topItem = Transaction::where('type', 'released')
            ->where('date_released', '>=', $startOfMonth)
            ->selectRaw('item_name_snapshot, COUNT(*) as c')
            ->groupBy('item_name_snapshot')
            ->orderByDesc('c')
            ->value('item_name_snapshot');

        return view('dashboard', compact(
            'totalInStock',
            'totalReleased',
            'pendingAck',
            'acknowledged',
            'pendingTransactions',
            'weeklyActivity',
            'topOffice',
            'topItem',
        ));
    }
}
```

- [ ] **Step 4: Run the test — expect pass**

```bash
php artisan test --filter=DashboardTest
```

Expected: PASS.

- [ ] **Step 5: Replace `resources/views/dashboard.blade.php`**

```blade
<x-app-layout>
    <x-page-header title="Dashboard" subtitle="MIS Office Inventory Overview"/>

    {{-- Row 1: Stat tiles --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4"
         x-data x-init="$stagger($el)" data-anim="stagger">
        <x-stat-tile label="Items in Stock"        :value="$totalInStock"  icon="cube"/>
        <x-stat-tile label="Total Released"        :value="$totalReleased" icon="paper-airplane"/>
        <x-stat-tile label="Pending Acknowledgment" :value="$pendingAck"   icon="clock" variant="hero"/>
        <x-stat-tile label="Acknowledged"          :value="$acknowledged"  icon="check-badge"/>
    </div>

    {{-- Row 2: hero activity + 2 side tiles --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-4">
        <x-bento-card variant="hero" class="lg:col-span-2">
            <p class="text-xs uppercase tracking-wide opacity-80 font-medium">Weekly Activity</p>
            <p class="text-3xl font-bold mt-1">{{ array_sum($weeklyActivity) }} <span class="text-sm font-medium opacity-80">releases / 7d</span></p>
            <div class="mt-3 h-16">
                <x-sparkline :data="$weeklyActivity" color="#ffffff"/>
            </div>
        </x-bento-card>

        <x-bento-card>
            <p class="text-xs uppercase tracking-wide text-ink-muted font-medium">Top Office (this month)</p>
            <p class="text-xl font-semibold text-ink-heading mt-2">{{ $topOffice ?? '—' }}</p>
        </x-bento-card>

        <x-bento-card>
            <p class="text-xs uppercase tracking-wide text-ink-muted font-medium">Top Item (this month)</p>
            <p class="text-xl font-semibold text-ink-heading mt-2">{{ $topItem ?? '—' }}</p>
        </x-bento-card>
    </div>

    {{-- Row 3: Pending acknowledgments table --}}
    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-heading">Pending Acknowledgments</h2>
            <a href="{{ route('acknowledge.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all</a>
        </div>

        @if($pendingTransactions->isEmpty())
            <x-empty-state icon="check-circle" title="All caught up" hint="No pending acknowledgments." />
        @else
            <x-table :headers="['Item', 'Released To', 'Office', 'Qty', 'Date', '']">
                @foreach($pendingTransactions as $tx)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->date_released }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('acknowledge.index') }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Acknowledge →</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>
</x-app-layout>
```

- [ ] **Step 6: Reload `/` in browser**

Expected:
- 4 stat tiles stagger in
- Numbers count up from 0
- "Pending" tile is the teal→cyan gradient
- Hero activity tile shows a white sparkline that draws in
- "Top Office" / "Top Item" show real names (if any releases exist)
- Pending acknowledgments table styled correctly

- [ ] **Step 7: Re-run the test**

```bash
php artisan test --filter=DashboardTest
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/DashboardController.php resources/views/dashboard.blade.php tests/Feature/DashboardTest.php
git commit -m "feat(dashboard): bento grid with weekly activity + top office/item"
```

---

### Task 13: Receive page — themed form, toast on success

**Files:**
- Modify: `resources/views/receive.blade.php` (full replacement)

- [ ] **Step 1: Replace `resources/views/receive.blade.php`**

```blade
<x-app-layout>
    <x-page-header title="Receive Item" subtitle="Record items received from Supplies & Properties Office"/>

    <x-bento-card class="max-w-3xl" x-data x-init="$el.classList.add('animate-slide-up')">
        <form method="POST" action="{{ route('receive.store') }}">
            @csrf

            <p class="text-sm font-semibold text-ink-heading mb-4">Item Details</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="name" required>Item Name</x-label>
                    <x-input id="name" name="name" :value="old('name')" required/>
                </div>
                <div>
                    <x-label for="category">Category</x-label>
                    <x-select id="category" name="category">
                        <option value="">Select category</option>
                        @foreach(['Office Supplies','Hardware','Peripherals','Consumables','Cables & Accessories','Networking','Furniture & Equipment','Other'] as $cat)
                            <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-label for="brand">Brand</x-label>
                    <x-input id="brand" name="brand" :value="old('brand')"/>
                </div>
                <div>
                    <x-label for="model_number">Model Number</x-label>
                    <x-input id="model_number" name="model_number" :value="old('model_number')"/>
                </div>
                <div>
                    <x-label for="serial_number">Serial Number</x-label>
                    <x-input id="serial_number" name="serial_number" :value="old('serial_number')"/>
                </div>
                <div>
                    <x-label for="unit">Unit</x-label>
                    <x-input id="unit" name="unit" :value="old('unit', 'pcs')" placeholder="pcs / ream / box"/>
                </div>
                <div>
                    <x-label for="qty" required>Quantity</x-label>
                    <x-input id="qty" name="qty" type="number" min="1" :value="old('qty')" required/>
                </div>
            </div>

            <p class="text-sm font-semibold text-ink-heading mb-4">Source &amp; Reference</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="received_from">Received From (S&amp;P Officer)</x-label>
                    <x-input id="received_from" name="received_from" :value="old('received_from')"/>
                </div>
                <div>
                    <x-label for="ris_iar_number">RIS / IAR Number</x-label>
                    <x-input id="ris_iar_number" name="ris_iar_number" :value="old('ris_iar_number')"/>
                </div>
                <div>
                    <x-label for="date_received" required>Date Received</x-label>
                    <x-input id="date_received" name="date_received" type="date" :value="old('date_received', date('Y-m-d'))" required/>
                </div>
                <div>
                    <x-label for="received_by">Received By</x-label>
                    <x-input id="received_by" name="received_by" :value="old('received_by', auth()->user()->name)"/>
                </div>
                <div class="md:col-span-2">
                    <x-label for="remarks">Remarks</x-label>
                    <x-textarea id="remarks" name="remarks">{{ old('remarks') }}</x-textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4"/>
                    Record Receipt
                </x-button>
                <x-button as="a" variant="ghost" href="{{ route('dashboard') }}">Cancel</x-button>
            </div>
        </form>
    </x-bento-card>
</x-app-layout>
```

- [ ] **Step 2: Reload `/receive` in browser**

Expected:
- Form card slides up on load
- All inputs are themed (teal focus ring)
- Submitting a valid form shows a green toast top-right after redirect

- [ ] **Step 3: Commit**

```bash
git add resources/views/receive.blade.php
git commit -m "feat(receive): themed bento form + toast success"
```

---

### Task 14: Release page — themed form + Alpine confirm modal

**Files:**
- Modify: `resources/views/release.blade.php` (full replacement)

- [ ] **Step 1: Replace `resources/views/release.blade.php`**

```blade
<x-app-layout>
    <x-page-header title="Release Item" subtitle="Release items to other offices"/>

    <x-bento-card class="max-w-3xl" x-data="releaseForm()" x-init="$el.classList.add('animate-slide-up')">
        <form method="POST" action="{{ route('release.store') }}" @submit.prevent="onSubmit">
            @csrf

            <p class="text-sm font-semibold text-ink-heading mb-4">Select Item</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <x-label for="item_id" required>Item</x-label>
                    <x-select id="item_id" name="item_id" required x-model="itemId" @change="onItemChange($event)">
                        <option value="">— Select item in stock —</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                    data-qty="{{ $item->current_qty }}"
                                    data-unit="{{ $item->unit }}"
                                    @selected(old('item_id') == $item->id)>
                                {{ $item->name }}{{ $item->brand ? ' — '.$item->brand : '' }}
                                ({{ $item->current_qty }} {{ $item->unit }} available)
                            </option>
                        @endforeach
                    </x-select>
                </div>
                <div>
                    <x-label>Available Qty</x-label>
                    <x-input readonly x-model="availableLabel" class="bg-surface-page text-ink-muted"/>
                </div>
                <div>
                    <x-label for="qty" required>Quantity to Release</x-label>
                    <x-input id="qty" name="qty" type="number" min="1" :value="old('qty')" required x-model.number="qty"/>
                </div>
            </div>

            <p class="text-sm font-semibold text-ink-heading mb-4">Release To</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <x-label for="released_to_office" required>Receiving Office</x-label>
                    <x-input id="released_to_office" name="released_to_office" :value="old('released_to_office')" required placeholder="e.g. Nursing Unit 3"/>
                </div>
                <div>
                    <x-label for="receiver_name" required>Receiver Name</x-label>
                    <x-input id="receiver_name" name="receiver_name" :value="old('receiver_name')" required/>
                </div>
                <div>
                    <x-label for="receiver_designation">Receiver Designation</x-label>
                    <x-input id="receiver_designation" name="receiver_designation" :value="old('receiver_designation')" placeholder="e.g. Head Nurse"/>
                </div>
                <div>
                    <x-label for="date_released" required>Date Released</x-label>
                    <x-input id="date_released" name="date_released" type="date" :value="old('date_released', date('Y-m-d'))" required/>
                </div>
                <div>
                    <x-label for="released_by">Released By</x-label>
                    <x-input id="released_by" name="released_by" :value="old('released_by', auth()->user()->name)"/>
                </div>
                <div>
                    <x-label for="purpose">Purpose</x-label>
                    <x-input id="purpose" name="purpose" :value="old('purpose')" placeholder="e.g. For printer replacement"/>
                </div>
                <div class="md:col-span-2">
                    <x-label for="remarks">Remarks</x-label>
                    <x-textarea id="remarks" name="remarks">{{ old('remarks') }}</x-textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-arrow-up-tray class="w-4 h-4"/>
                    Release Item
                </x-button>
                <x-button as="a" variant="ghost" href="{{ route('dashboard') }}">Cancel</x-button>
            </div>

            {{-- Confirm modal --}}
            <div x-show="confirming"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 max-w-sm mx-4 animate-pop">
                    <h3 class="text-base font-semibold text-ink-heading mb-2">Large release</h3>
                    <p class="text-sm text-ink-body mb-5">
                        You're releasing <strong x-text="qty"></strong> out of <strong x-text="available"></strong> available
                        (<span x-text="Math.round((qty / available) * 100)"></span>%). Continue?
                    </p>
                    <div class="flex justify-end gap-2">
                        <x-button type="button" variant="ghost" @click="confirming = false">Cancel</x-button>
                        <x-button type="button" variant="primary" @click="confirm()">Yes, release</x-button>
                    </div>
                </div>
            </div>
        </form>
    </x-bento-card>

    <script>
        function releaseForm() {
            return {
                itemId: '{{ old('item_id', '') }}',
                qty: {{ (int) old('qty', 0) }},
                available: 0,
                unit: '',
                confirming: false,
                get availableLabel() {
                    return this.available ? `${this.available} ${this.unit}` : '';
                },
                init() {
                    if (this.itemId) this.refreshAvailable();
                },
                onItemChange(e) {
                    this.refreshAvailable(e.target);
                },
                refreshAvailable(selectEl) {
                    selectEl = selectEl || document.getElementById('item_id');
                    const opt = selectEl.options[selectEl.selectedIndex];
                    this.available = parseInt(opt?.dataset.qty || 0, 10);
                    this.unit = opt?.dataset.unit || '';
                },
                onSubmit(e) {
                    if (this.available > 0 && this.qty > this.available * 0.5 && !this.confirming) {
                        this.confirming = true;
                        return;
                    }
                    e.target.submit();
                },
                confirm() {
                    this.confirming = false;
                    document.querySelector('form').submit();
                },
            }
        }
    </script>
</x-app-layout>
```

- [ ] **Step 2: Reload `/release` in browser**

Expected:
- Form slides up
- Selecting an item populates "Available Qty"
- Submitting with `qty <= 50% of available` → submits straight through
- Submitting with `qty > 50% of available` → confirm modal pops up; cancel returns, confirm proceeds
- Success → toast top-right after redirect

- [ ] **Step 3: Commit**

```bash
git add resources/views/release.blade.php
git commit -m "feat(release): themed form + Alpine confirm modal for large releases"
```

---

### Task 15: Acknowledge page — Alpine card list with animated row removal

**Files:**
- Modify: `resources/views/acknowledge.blade.php` (full replacement)

- [ ] **Step 1: Replace `resources/views/acknowledge.blade.php`**

```blade
<x-app-layout>
    <x-page-header title="Acknowledge Receipt" subtitle="Record acknowledgment from receiving offices"/>

    {{-- Pending --}}
    <x-bento-card :padded="false" class="mb-6">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-heading">
                Awaiting Acknowledgment <span class="text-ink-muted">({{ $pending->count() }})</span>
            </h2>
        </div>

        @if($pending->isEmpty())
            <x-empty-state icon="check-circle" title="All caught up" hint="No pending acknowledgments."/>
        @else
            <div class="divide-y divide-surface-border"
                 x-data="ackList()" x-init="$stagger($el)" data-anim="stagger">
                @foreach($pending as $tx)
                    <div :id="'tx-' + {{ $tx->id }}" class="px-6 py-4 transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-ink-heading">{{ $tx->item_name_snapshot }}</p>
                                <p class="text-xs text-ink-body mt-1">
                                    {{ $tx->qty }} {{ $tx->unit }} •
                                    Released to: <span class="text-ink-heading">{{ $tx->receiver_name }}{{ $tx->receiver_designation ? ' ('.$tx->receiver_designation.')' : '' }}</span>
                                    • {{ $tx->released_to_office }}
                                    • {{ $tx->date_released }}
                                </p>
                                @if($tx->purpose)
                                    <p class="text-xs text-ink-muted mt-1">Purpose: {{ $tx->purpose }}</p>
                                @endif
                            </div>
                            <x-button type="button" variant="primary"
                                @click="openModal({{ $tx->id }}, '{{ addslashes($tx->item_name_snapshot) }}', '{{ $tx->qty }} {{ $tx->unit }}', '{{ addslashes($tx->receiver_name) }}', '{{ addslashes($tx->released_to_office) }}')">
                                <x-heroicon-o-check class="w-4 h-4"/>
                                Acknowledge
                            </x-button>
                        </div>
                    </div>
                @endforeach

                {{-- Modal --}}
                <div x-show="modal.open" x-cloak x-transition.opacity
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 w-full max-w-md mx-4 animate-pop">
                        <h3 class="text-base font-semibold text-ink-heading mb-1">Record Acknowledgment</h3>
                        <p class="text-xs text-ink-muted mb-4">
                            <strong class="text-ink-heading" x-text="modal.item"></strong> • <span x-text="modal.qty"></span>
                            <br>Released to: <span x-text="modal.receiver"></span> • <span x-text="modal.office"></span>
                        </p>

                        <form @submit.prevent="submit">
                            <div class="space-y-4">
                                <div>
                                    <x-label for="ack-by" required>Acknowledged By</x-label>
                                    <x-input id="ack-by" name="acknowledged_by_name" required x-model="form.acknowledged_by_name"/>
                                </div>
                                <div>
                                    <x-label for="ack-date" required>Date Acknowledged</x-label>
                                    <x-input id="ack-date" name="acknowledged_date" type="date" required x-model="form.acknowledged_date"/>
                                </div>
                                <div>
                                    <x-label for="ack-remarks">Remarks</x-label>
                                    <x-textarea id="ack-remarks" name="acknowledgment_remarks" rows="2" x-model="form.acknowledgment_remarks" placeholder="e.g. Items received in good condition"/>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 mt-5">
                                <x-button type="button" variant="ghost" @click="modal.open = false">Cancel</x-button>
                                <x-button type="submit" variant="primary" x-bind:disabled="submitting" x-text="submitting ? 'Saving…' : 'Confirm'"></x-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </x-bento-card>

    {{-- Acknowledged (history) --}}
    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">
                Acknowledged <span class="text-ink-muted">({{ $acknowledged->count() }})</span>
            </h2>
        </div>
        @if($acknowledged->isEmpty())
            <x-empty-state icon="document-text" title="No history yet" hint="Acknowledged transactions will appear here."/>
        @else
            <x-table :headers="['Item', 'Qty', 'Released To', 'Office', 'Acknowledged By', 'Date', 'Remarks']">
                @foreach($acknowledged as $tx)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->acknowledged_by_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->acknowledged_date }}</td>
                        <td class="px-6 py-3 text-ink-muted">{{ $tx->acknowledgment_remarks ?? '—' }}</td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>

    <script>
        function ackList() {
            return {
                modal: { open: false, id: null, item: '', qty: '', receiver: '', office: '' },
                form: {
                    acknowledged_by_name: '',
                    acknowledged_date: new Date().toISOString().slice(0, 10),
                    acknowledgment_remarks: '',
                },
                submitting: false,
                openModal(id, item, qty, receiver, office) {
                    this.modal = { open: true, id, item, qty, receiver, office };
                },
                async submit() {
                    if (this.submitting) return;
                    this.submitting = true;

                    const res = await fetch(`/acknowledge/${this.modal.id}`, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(this.form),
                    });

                    this.submitting = false;

                    if (!res.ok) {
                        alert('Could not record acknowledgment. Please try again.');
                        return;
                    }

                    // Animate row out, then remove
                    const row = document.getElementById(`tx-${this.modal.id}`);
                    if (row) {
                        row.classList.add('animate-pop-out');
                        setTimeout(() => row.remove(), 250);
                    }
                    this.modal.open = false;
                    this.form.acknowledgment_remarks = '';
                },
            }
        }
    </script>
</x-app-layout>
```

- [ ] **Step 2: Confirm `AcknowledgeController@update` returns JSON when requested**

Open `app/Http/Controllers/AcknowledgeController.php` and verify the `update` method redirects on success (existing behavior). For the Alpine fetch to work without a full page reload, we accept either response — the JS only checks `res.ok`. If the controller redirects (3xx), `fetch` still resolves with `ok: true` after following the redirect, so this works unchanged. **No controller edit required** — but verify by reading the file. If `update` does anything that would block AJAX (e.g. an `abort_if`), note it and address before continuing.

- [ ] **Step 3: Reload `/acknowledge` in browser**

Expected:
- Pending rows stagger in
- Clicking "Acknowledge" pops modal
- Submitting calls the existing route, the row animates out (pop-out) and is removed
- The acknowledged-history table styles correctly
- Refresh the page — the now-acknowledged row should appear in the bottom table

- [ ] **Step 4: Commit**

```bash
git add resources/views/acknowledge.blade.php
git commit -m "feat(acknowledge): inline Alpine confirm + animated row removal"
```

---

### Task 16: Transactions — index filter bar + show two-column

**Files:**
- Modify: `resources/views/transactions.blade.php` (full replacement)
- Modify: `resources/views/transactions-show.blade.php` (full replacement)

- [ ] **Step 1: Replace `resources/views/transactions.blade.php`**

```blade
<x-app-layout>
    <x-page-header title="Transactions" subtitle="Full log of all received and released items"/>

    {{-- Filter bar --}}
    <x-bento-card class="mb-4">
        <form method="GET" action="{{ route('transactions.index') }}"
              class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div class="md:col-span-2">
                <x-label for="search">Search</x-label>
                <x-input id="search" name="search" :value="request('search')" placeholder="Item, office, person…"/>
            </div>
            <div>
                <x-label for="type">Type</x-label>
                <x-select id="type" name="type">
                    <option value="">All</option>
                    <option value="received" @selected(request('type') === 'received')>Received</option>
                    <option value="released" @selected(request('type') === 'released')>Released</option>
                </x-select>
            </div>
            <div>
                <x-label for="status">Status</x-label>
                <x-select id="status" name="status">
                    <option value="">All</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="acknowledged" @selected(request('status') === 'acknowledged')>Acknowledged</option>
                </x-select>
            </div>
            <div>
                <x-label for="date_from">From</x-label>
                <x-input id="date_from" name="date_from" type="date" :value="request('date_from')"/>
            </div>
            <div>
                <x-label for="date_to">To</x-label>
                <x-input id="date_to" name="date_to" type="date" :value="request('date_to')"/>
            </div>
            <div class="md:col-span-6 flex justify-end gap-2">
                @if(request()->hasAny(['search','type','status','date_from','date_to']))
                    <x-button as="a" variant="ghost" href="{{ route('transactions.index') }}">Clear</x-button>
                @endif
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-funnel class="w-4 h-4"/>
                    Apply filters
                </x-button>
            </div>
        </form>
    </x-bento-card>

    <x-bento-card :padded="false">
        @if($transactions->isEmpty())
            <x-empty-state icon="inbox" title="No transactions found" hint="Adjust filters or record a new transaction."/>
        @else
            <x-table :headers="['Type','Item','Qty','From / To','Office','Date','Status','']">
                @foreach($transactions as $tx)
                    <x-table.row>
                        <td class="px-6 py-3">
                            @if($tx->type === 'received')
                                <x-status-badge status="received">IN</x-status-badge>
                            @else
                                <x-status-badge status="released">OUT</x-status-badge>
                            @endif
                        </td>
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->received_from : $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? 'S&P Office' : $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->date_received : $tx->date_released }}</td>
                        <td class="px-6 py-3">
                            @if($tx->type === 'received')
                                <x-status-badge status="received"/>
                            @elseif($tx->acknowledgment_status === 'acknowledged')
                                <x-status-badge status="acknowledged"/>
                            @else
                                <x-status-badge status="pending"/>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('transactions.show', $tx->id) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">View →</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="px-6 py-4 border-t border-surface-border">
                {{ $transactions->withQueryString()->links() }}
            </div>
        @endif
    </x-bento-card>
</x-app-layout>
```

- [ ] **Step 2: Replace `resources/views/transactions-show.blade.php`**

```blade
<x-app-layout>
    <div class="mb-4">
        <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Back to Transactions
        </a>
    </div>

    <x-page-header :title="$transaction->item_name_snapshot"
                   :subtitle="'Transaction #' . $transaction->id">
        <x-slot:actions>
            @if($transaction->type === 'received')
                <x-status-badge status="received">IN — Received</x-status-badge>
            @elseif($transaction->acknowledgment_status === 'acknowledged')
                <x-status-badge status="acknowledged">OUT — Acknowledged</x-status-badge>
            @else
                <x-status-badge status="pending">OUT — Pending</x-status-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Left: details (col-span-2) --}}
        <x-bento-card class="lg:col-span-2 space-y-6">
            <div>
                <p class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-3">Item Details</p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="bg-surface-page rounded-lg p-3">
                        <p class="text-xs text-ink-muted mb-1">Item</p>
                        <p class="font-medium text-ink-heading">{{ $transaction->item_name_snapshot }}</p>
                    </div>
                    <div class="bg-surface-page rounded-lg p-3">
                        <p class="text-xs text-ink-muted mb-1">Quantity</p>
                        <p class="font-medium text-ink-heading">{{ $transaction->qty }} {{ $transaction->unit }}</p>
                    </div>
                </div>
            </div>

            @if($transaction->type === 'received')
                <div>
                    <p class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-3">Receipt Details</p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Received From</p><p class="font-medium text-ink-heading">{{ $transaction->received_from ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">RIS / IAR No.</p><p class="font-medium text-ink-heading">{{ $transaction->ris_iar_number ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Date Received</p><p class="font-medium text-ink-heading">{{ $transaction->date_received ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Received By</p><p class="font-medium text-ink-heading">{{ $transaction->receivedBy->name ?? '—' }}</p></div>
                    </div>
                </div>
            @endif

            @if($transaction->type === 'released')
                <div>
                    <p class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-3">Release Details</p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Released To</p><p class="font-medium text-ink-heading">{{ $transaction->receiver_name ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Designation</p><p class="font-medium text-ink-heading">{{ $transaction->receiver_designation ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Office</p><p class="font-medium text-ink-heading">{{ $transaction->released_to_office ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Date Released</p><p class="font-medium text-ink-heading">{{ $transaction->date_released ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Released By</p><p class="font-medium text-ink-heading">{{ $transaction->releasedBy->name ?? '—' }}</p></div>
                        <div class="bg-surface-page rounded-lg p-3"><p class="text-xs text-ink-muted mb-1">Purpose</p><p class="font-medium text-ink-heading">{{ $transaction->purpose ?? '—' }}</p></div>
                    </div>
                </div>
            @endif

            @if($transaction->remarks)
                <div>
                    <p class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-2">Remarks</p>
                    <p class="text-sm text-ink-body bg-surface-page rounded-lg p-3">{{ $transaction->remarks }}</p>
                </div>
            @endif
        </x-bento-card>

        {{-- Right: acknowledgment / metadata --}}
        <x-bento-card class="space-y-4">
            <p class="text-xs font-medium text-ink-muted uppercase tracking-wide">Acknowledgment</p>

            @if($transaction->type === 'received')
                <p class="text-sm text-ink-body">No acknowledgment required for receipt.</p>
            @elseif($transaction->acknowledgment_status === 'acknowledged')
                <div class="space-y-2">
                    <div class="bg-emerald-50 rounded-lg p-3">
                        <p class="text-xs text-emerald-700 mb-1">Acknowledged By</p>
                        <p class="font-medium text-emerald-900">{{ $transaction->acknowledged_by_name }}</p>
                    </div>
                    <div class="bg-emerald-50 rounded-lg p-3">
                        <p class="text-xs text-emerald-700 mb-1">Date</p>
                        <p class="font-medium text-emerald-900">{{ $transaction->acknowledged_date }}</p>
                    </div>
                    @if($transaction->acknowledgment_remarks)
                        <div class="bg-emerald-50 rounded-lg p-3">
                            <p class="text-xs text-emerald-700 mb-1">Remarks</p>
                            <p class="font-medium text-emerald-900">{{ $transaction->acknowledgment_remarks }}</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="bg-rose-50 rounded-lg p-4 text-sm text-rose-800">
                    Pending acknowledgment from {{ $transaction->receiver_name }}.
                    <a href="{{ route('acknowledge.index') }}" class="underline ml-1">Record now</a>
                </div>
            @endif
        </x-bento-card>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Reload `/transactions` and click into a row**

Expected:
- Filter bar styled, all filters apply correctly
- Status pills are themed via `x-status-badge`
- Show page is two columns at `lg:` breakpoint; left card has details, right card has ack info

- [ ] **Step 4: Commit**

```bash
git add resources/views/transactions.blade.php resources/views/transactions-show.blade.php
git commit -m "feat(transactions): themed filter bar + two-column detail layout"
```

---

### Task 17: Items — card grid with sparklines + show page

**Files:**
- Modify: `app/Http/Controllers/ItemController.php`
- Modify: `resources/views/items.blade.php` (full replacement)
- Modify: `resources/views/items-show.blade.php` (full replacement)
- Create: `tests/Feature/ItemIndexTest.php`

- [ ] **Step 1: Write a failing test**

Create `tests/Feature/ItemIndexTest.php`:

```php
<?php

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;

test('items index attaches movement30 (array of 30 daily net deltas) to each item', function () {
    $user = User::factory()->create();
    $item = Item::create([
        'name' => 'USB Cable', 'unit' => 'pcs',
        'current_qty' => 10, 'total_qty_received' => 10,
    ]);
    Transaction::create([
        'item_id' => $item->id, 'type' => 'received',
        'item_name_snapshot' => 'USB Cable', 'qty' => 5, 'unit' => 'pcs',
        'date_received' => now()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('items.index'));

    $response->assertOk();
    $items = $response->viewData('items');
    $first = $items->first();

    expect($first->movement30)
        ->toBeArray()
        ->toHaveCount(30);
});
```

- [ ] **Step 2: Run — expect failure**

```bash
php artisan test --filter=ItemIndexTest
```

Expected: FAIL — `movement30` undefined on the item.

- [ ] **Step 3: Replace `app/Http/Controllers/ItemController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('brand', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        $items = $query->latest()->paginate(24);

        // Attach 30-day net movement array to each paginated item.
        $items->getCollection()->transform(function (Item $item) {
            $item->movement30 = $this->movement30($item);
            return $item;
        });

        $categories = Item::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        return view('items', compact('items', 'categories'));
    }

    public function show(Item $item)
    {
        $transactions = $item->transactions()->latest()->get();
        $movement30 = $this->movement30($item);
        return view('items-show', compact('item', 'transactions', 'movement30'));
    }

    /**
     * Return an array of 30 daily net stock deltas (oldest → newest).
     * Net delta = sum(received qty) − sum(released qty) for that day.
     */
    private function movement30(Item $item): array
    {
        $start = Carbon::today()->subDays(29);

        $rows = Transaction::where('item_id', $item->id)
            ->whereDate('created_at', '>=', $start)
            ->get(['type', 'qty', 'created_at']);

        $byDay = [];
        for ($i = 0; $i < 30; $i++) {
            $byDay[$start->copy()->addDays($i)->toDateString()] = 0;
        }
        foreach ($rows as $r) {
            $day = $r->created_at->toDateString();
            if (!array_key_exists($day, $byDay)) continue;
            $byDay[$day] += $r->type === 'received' ? $r->qty : -$r->qty;
        }
        return array_values($byDay);
    }
}
```

- [ ] **Step 4: Run the test — expect pass**

```bash
php artisan test --filter=ItemIndexTest
```

Expected: PASS.

- [ ] **Step 5: Replace `resources/views/items.blade.php`**

```blade
<x-app-layout>
    <x-page-header title="Inventory" subtitle="All items received by the MIS Office">
        <x-slot:actions>
            <x-button as="a" variant="primary" href="{{ route('receive.index') }}">
                <x-heroicon-o-plus class="w-4 h-4"/> Receive Item
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Filters --}}
    <x-bento-card class="mb-4">
        <form method="GET" action="{{ route('items.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <x-label for="search">Search</x-label>
                <x-input id="search" name="search" :value="request('search')" placeholder="Item name or brand…"/>
            </div>
            <div>
                <x-label for="category">Category</x-label>
                <x-select id="category" name="category">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="flex gap-2">
                @if(request()->hasAny(['search','category']))
                    <x-button as="a" variant="ghost" href="{{ route('items.index') }}">Clear</x-button>
                @endif
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-funnel class="w-4 h-4"/> Filter
                </x-button>
            </div>
        </form>
    </x-bento-card>

    {{-- Card grid --}}
    @if($items->isEmpty())
        <x-bento-card><x-empty-state icon="cube" title="No items found" hint="Adjust filters or receive a new item."/></x-bento-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"
             x-data x-init="$stagger($el)" data-anim="stagger">
            @foreach($items as $item)
                <a href="{{ route('items.show', $item->id) }}"
                   class="block bg-surface-tile rounded-2xl shadow-tile p-5 transition hover:-translate-y-1 hover:shadow-tile-hover">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-ink-heading truncate">{{ $item->name }}</p>
                            <p class="text-xs text-ink-muted mt-0.5 truncate">
                                {{ $item->brand ?? '—' }}{{ $item->category ? ' • '.$item->category : '' }}
                            </p>
                        </div>
                        @if($item->current_qty > 0)
                            <span class="bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-1 rounded-full shrink-0">In stock</span>
                        @else
                            <span class="bg-rose-50 text-rose-700 text-xs font-medium px-2.5 py-1 rounded-full shrink-0">Out</span>
                        @endif
                    </div>
                    <div class="mt-4 flex items-end justify-between">
                        <div>
                            <p class="text-3xl font-bold text-ink-heading">{{ $item->current_qty }}</p>
                            <p class="text-xs text-ink-muted">{{ $item->unit }} on hand</p>
                        </div>
                        <div class="w-24 h-10">
                            <x-sparkline :data="$item->movement30"/>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $items->withQueryString()->links() }}
        </div>
    @endif
</x-app-layout>
```

- [ ] **Step 6: Replace `resources/views/items-show.blade.php`**

```blade
<x-app-layout>
    <div class="mb-4">
        <a href="{{ route('items.index') }}" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Back to Inventory
        </a>
    </div>

    <x-page-header :title="$item->name"
                   :subtitle="trim(($item->brand ?? '') . ' ' . ($item->model_number ? '— ' . $item->model_number : '')) ?: null">
        <x-slot:actions>
            @if($item->current_qty > 0)
                <x-status-badge status="acknowledged">{{ $item->current_qty }} {{ $item->unit }} in stock</x-status-badge>
            @else
                <x-status-badge status="pending">Out of stock</x-status-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Metadata --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <x-bento-card>
            <p class="text-xs text-ink-muted uppercase tracking-wide">Category</p>
            <p class="font-medium text-ink-heading mt-1">{{ $item->category ?? '—' }}</p>
        </x-bento-card>
        <x-bento-card>
            <p class="text-xs text-ink-muted uppercase tracking-wide">Serial No.</p>
            <p class="font-medium text-ink-heading mt-1">{{ $item->serial_number ?? '—' }}</p>
        </x-bento-card>
        <x-bento-card>
            <p class="text-xs text-ink-muted uppercase tracking-wide">Total Received</p>
            <p class="font-medium text-ink-heading mt-1">{{ $item->total_qty_received }} {{ $item->unit }}</p>
        </x-bento-card>
        <x-bento-card>
            <p class="text-xs text-ink-muted uppercase tracking-wide">Current Stock</p>
            <p class="font-medium text-ink-heading mt-1" x-data x-count-up>{{ $item->current_qty }}</p>
        </x-bento-card>
    </div>

    {{-- 30-day movement --}}
    <x-bento-card variant="hero" class="mb-4">
        <p class="text-xs uppercase tracking-wide opacity-80 font-medium">30-day movement</p>
        <div class="mt-3 h-20">
            <x-sparkline :data="$movement30" color="#ffffff"/>
        </div>
    </x-bento-card>

    {{-- History --}}
    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">Transaction History</h2>
        </div>
        @if($transactions->isEmpty())
            <x-empty-state icon="document-text" title="No transactions yet" hint="Receipts and releases will appear here."/>
        @else
            <x-table :headers="['Type','Qty','From / To','Office','Date','Status','']">
                @foreach($transactions as $tx)
                    <x-table.row>
                        <td class="px-6 py-3">
                            @if($tx->type === 'received')
                                <x-status-badge status="received">IN</x-status-badge>
                            @else
                                <x-status-badge status="released">OUT</x-status-badge>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->received_from : $tx->receiver_name }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? 'S&P Office' : $tx->released_to_office }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->date_received : $tx->date_released }}</td>
                        <td class="px-6 py-3">
                            @if($tx->type === 'received')
                                <x-status-badge status="received"/>
                            @elseif($tx->acknowledgment_status === 'acknowledged')
                                <x-status-badge status="acknowledged"/>
                            @else
                                <x-status-badge status="pending"/>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('transactions.show', $tx->id) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">View →</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>
</x-app-layout>
```

- [ ] **Step 7: Reload `/items` and click an item**

Expected:
- Index shows a 3-col card grid (1-col on mobile)
- Cards stagger in; each shows a sparkline of last 30 days
- Hover lifts the card
- Show page has count-up on current stock, a hero gradient card with full-width sparkline, themed history table

- [ ] **Step 8: Re-run all tests**

```bash
php artisan test
```

Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/ItemController.php resources/views/items.blade.php resources/views/items-show.blade.php tests/Feature/ItemIndexTest.php
git commit -m "feat(items): card grid + sparklines + 30-day movement on show"
```

---

### Task 18: Auth pages — split layout

**Files:**
- Modify: `resources/views/layouts/guest.blade.php` (full replacement)
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/auth/register.blade.php`
- Modify: `resources/views/auth/forgot-password.blade.php`
- Modify: `resources/views/auth/reset-password.blade.php`
- Modify: `resources/views/auth/confirm-password.blade.php`
- Modify: `resources/views/auth/verify-email.blade.php`

- [ ] **Step 1: Replace `resources/views/layouts/guest.blade.php`**

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MIS Inventory') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-page text-ink-body antialiased">
    <div class="min-h-screen flex">
        {{-- Left: brand panel --}}
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-primary-600 to-accent-500 text-white p-12 flex-col justify-between">
            <div>
                <x-application-logo class="w-12 h-12 fill-current text-white"/>
                <p class="mt-6 text-sm uppercase tracking-widest opacity-80">La Union Medical Center</p>
                <h1 class="mt-2 text-3xl font-bold">MIS Office Inventory</h1>
                <p class="mt-4 text-white/80 max-w-md text-sm leading-relaxed">
                    Track items received, released, and acknowledged across hospital offices — from a single, unified workspace.
                </p>
            </div>
            <p class="text-xs opacity-70">© {{ date('Y') }} La Union Medical Center</p>
        </div>

        {{-- Right: form panel --}}
        <div class="w-full lg:w-1/2 flex items-center justify-center p-6">
            <div class="w-full max-w-md animate-slide-up">
                <div class="lg:hidden mb-6 flex justify-center">
                    <x-application-logo class="w-12 h-12 fill-current text-primary-600"/>
                </div>
                <x-bento-card class="p-8">
                    {{ $slot }}
                </x-bento-card>
            </div>
        </div>
    </div>

    <x-toast-container/>
</body>
</html>
```

- [ ] **Step 2: Replace `resources/views/auth/login.blade.php`**

```blade
<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Welcome back</h2>
    <p class="text-sm text-ink-muted mb-6">Sign in to continue.</p>

    <x-auth-session-status class="mb-4" :status="session('status')"/>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-label for="email" required>Email</x-label>
            <x-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username"/>
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-label for="password" required>Password</x-label>
            <x-input id="password" type="password" name="password" required autocomplete="current-password"/>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <label for="remember_me" class="inline-flex items-center text-sm text-ink-body">
            <input id="remember_me" type="checkbox" class="rounded border-surface-border text-primary-600 focus:ring-primary-500" name="remember">
            <span class="ms-2">Remember me</span>
        </label>

        <div class="flex items-center justify-between pt-2">
            @if (Route::has('password.request'))
                <a class="text-xs text-ink-muted hover:text-primary-600" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
            <x-button type="submit" variant="primary">Log in</x-button>
        </div>
    </form>
</x-guest-layout>
```

- [ ] **Step 3: Replace `resources/views/auth/register.blade.php`**

```blade
<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Create an account</h2>
    <p class="text-sm text-ink-muted mb-6">Register a new MIS Office user.</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <x-label for="name" required>Name</x-label>
            <x-input id="name" name="name" :value="old('name')" required autofocus autocomplete="name"/>
            @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-label for="email" required>Email</x-label>
            <x-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username"/>
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-label for="password" required>Password</x-label>
            <x-input id="password" type="password" name="password" required autocomplete="new-password"/>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-label for="password_confirmation" required>Confirm password</x-label>
            <x-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"/>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="text-xs text-ink-muted hover:text-primary-600" href="{{ route('login') }}">Already registered?</a>
            <x-button type="submit" variant="primary">Register</x-button>
        </div>
    </form>
</x-guest-layout>
```

- [ ] **Step 4: Replace `resources/views/auth/forgot-password.blade.php`**

```blade
<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Forgot your password?</h2>
    <p class="text-sm text-ink-muted mb-6">Enter your email and we'll send a reset link.</p>

    <x-auth-session-status class="mb-4" :status="session('status')"/>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <x-label for="email" required>Email</x-label>
            <x-input id="email" type="email" name="email" :value="old('email')" required autofocus/>
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <x-button type="submit" variant="primary">Email password reset link</x-button>
        </div>
    </form>
</x-guest-layout>
```

- [ ] **Step 5: Replace `resources/views/auth/reset-password.blade.php`**

```blade
<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Reset password</h2>
    <p class="text-sm text-ink-muted mb-6">Choose a new password for your account.</p>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-label for="email" required>Email</x-label>
            <x-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus/>
            @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-label for="password" required>Password</x-label>
            <x-input id="password" type="password" name="password" required autocomplete="new-password"/>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <x-label for="password_confirmation" required>Confirm password</x-label>
            <x-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"/>
        </div>
        <div class="flex justify-end">
            <x-button type="submit" variant="primary">Reset password</x-button>
        </div>
    </form>
</x-guest-layout>
```

- [ ] **Step 6: Replace `resources/views/auth/confirm-password.blade.php`**

```blade
<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Confirm password</h2>
    <p class="text-sm text-ink-muted mb-6">Please confirm your password before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <div>
            <x-label for="password" required>Password</x-label>
            <x-input id="password" type="password" name="password" required autocomplete="current-password" autofocus/>
            @error('password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="flex justify-end">
            <x-button type="submit" variant="primary">Confirm</x-button>
        </div>
    </form>
</x-guest-layout>
```

- [ ] **Step 7: Replace `resources/views/auth/verify-email.blade.php`**

```blade
<x-guest-layout>
    <h2 class="text-xl font-semibold text-ink-heading mb-1">Verify your email</h2>
    <p class="text-sm text-ink-muted mb-6">
        We sent a verification link to your email. Click it to activate your account.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm bg-emerald-50 text-emerald-800 rounded-lg px-3 py-2">
            A new verification link has been sent.
        </div>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs text-ink-muted hover:text-primary-600">Log out</button>
        </form>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit" variant="primary">Resend verification email</x-button>
        </form>
    </div>
</x-guest-layout>
```

- [ ] **Step 8: Log out and verify each auth screen**

In an incognito tab visit:
- `/login` — split layout, brand panel on left, form on right
- `/register` — same shell
- `/forgot-password` — same
- `/reset-password` — only renders from an email link; eyeball any styling oddities
- `/verify-email`, `/confirm-password` — visit `/email/verify` or `/confirm-password` if you can reach them; if not, accept that they share the same layout

- [ ] **Step 9: Commit**

```bash
git add resources/views/layouts/guest.blade.php resources/views/auth
git commit -m "feat(auth): split-layout login + themed auth screens"
```

---

### Task 19: Profile pages — wrap in bento + themed forms

**Files:**
- Modify: `resources/views/profile/edit.blade.php`
- Modify: `resources/views/profile/partials/update-profile-information-form.blade.php`
- Modify: `resources/views/profile/partials/update-password-form.blade.php`
- Modify: `resources/views/profile/partials/delete-user-form.blade.php`

- [ ] **Step 1: Read each profile partial first**

```bash
ls resources/views/profile/partials/
```

(They exist from Breeze. We'll lightly rewrap them.)

- [ ] **Step 2: Replace `resources/views/profile/edit.blade.php`**

```blade
<x-app-layout>
    <x-page-header title="Profile" subtitle="Manage your account information and password"/>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 max-w-5xl">
        <x-bento-card class="lg:col-span-2">
            @include('profile.partials.update-profile-information-form')
        </x-bento-card>

        <x-bento-card>
            @include('profile.partials.update-password-form')
        </x-bento-card>

        <x-bento-card class="lg:col-span-3 border border-danger/20 bg-rose-50/50">
            @include('profile.partials.delete-user-form')
        </x-bento-card>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Replace `resources/views/profile/partials/update-profile-information-form.blade.php`**

```blade
<header class="mb-4">
    <h2 class="text-base font-semibold text-ink-heading">Profile Information</h2>
    <p class="text-xs text-ink-muted mt-1">Update your name and email address.</p>
</header>

<form method="post" action="{{ route('profile.update') }}" class="space-y-4">
    @csrf
    @method('patch')

    <div>
        <x-label for="name" required>Name</x-label>
        <x-input id="name" name="name" :value="old('name', auth()->user()->name)" required autofocus autocomplete="name"/>
        @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <x-label for="email" required>Email</x-label>
        <x-input id="email" type="email" name="email" :value="old('email', auth()->user()->email)" required autocomplete="username"/>
        @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <p class="mt-2 text-xs text-ink-muted">
                Your email address is unverified.
                <button form="send-verification" class="underline text-primary-600 hover:text-primary-700">Resend verification email</button>
            </p>
            @if (session('status') === 'verification-link-sent')
                <p class="mt-2 text-xs text-emerald-700">A new verification link has been sent.</p>
            @endif
        @endif
    </div>

    <div class="flex items-center gap-3 pt-2">
        <x-button type="submit" variant="primary">Save</x-button>
        @if (session('status') === 'profile-updated')
            <p class="text-xs text-emerald-700">Saved.</p>
        @endif
    </div>
</form>

<form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>
```

- [ ] **Step 4: Replace `resources/views/profile/partials/update-password-form.blade.php`**

```blade
<header class="mb-4">
    <h2 class="text-base font-semibold text-ink-heading">Update Password</h2>
    <p class="text-xs text-ink-muted mt-1">Use a strong password.</p>
</header>

<form method="post" action="{{ route('password.update') }}" class="space-y-4">
    @csrf
    @method('put')

    <div>
        <x-label for="current_password" required>Current</x-label>
        <x-input id="current_password" type="password" name="current_password" autocomplete="current-password"/>
        @error('updatePassword.current_password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <x-label for="password" required>New</x-label>
        <x-input id="password" type="password" name="password" autocomplete="new-password"/>
        @error('updatePassword.password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <x-label for="password_confirmation" required>Confirm</x-label>
        <x-input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"/>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <x-button type="submit" variant="primary">Save</x-button>
        @if (session('status') === 'password-updated')
            <p class="text-xs text-emerald-700">Saved.</p>
        @endif
    </div>
</form>
```

- [ ] **Step 5: Replace `resources/views/profile/partials/delete-user-form.blade.php`**

```blade
<header class="mb-4">
    <h2 class="text-base font-semibold text-danger">Delete Account</h2>
    <p class="text-xs text-ink-body mt-1">
        Once deleted, all resources and data will be permanently removed. Download anything you want to keep first.
    </p>
</header>

<div x-data="{ open: false }">
    <x-button type="button" variant="danger" @click="open = true">Delete account</x-button>

    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 w-full max-w-md mx-4 animate-pop">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <h3 class="text-base font-semibold text-ink-heading mb-2">Are you sure?</h3>
                <p class="text-sm text-ink-body mb-4">
                    This will permanently delete your account. Enter your password to confirm.
                </p>

                <x-label for="password" required>Password</x-label>
                <x-input id="password" type="password" name="password" required/>
                @error('userDeletion.password') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror

                <div class="flex justify-end gap-2 mt-5">
                    <x-button type="button" variant="ghost" @click="open = false">Cancel</x-button>
                    <x-button type="submit" variant="danger">Delete account</x-button>
                </div>
            </form>
        </div>
    </div>
</div>
```

- [ ] **Step 6: Visit `/profile` and verify**

Expected: three bento cards, themed forms, themed delete confirmation modal (Alpine).

- [ ] **Step 7: Commit**

```bash
git add resources/views/profile
git commit -m "feat(profile): bento layout + themed forms + Alpine delete modal"
```

---

## Phase 5 — Cleanup & verification

### Task 20: README update + final verification pass

**Files:**
- Modify: `README.md`
- Read-only: every redesigned page

- [ ] **Step 1: Append a "Local development" section to `README.md`**

Add at the end of the existing `README.md`:

```markdown

## Local Development

Run the dev server (Vite + Laravel + queue + logs in parallel):

```bash
composer dev
```

Or just the frontend with HMR:

```bash
npm run dev
```

Production build:

```bash
npm run build
```

The UI is themed via `tailwind.config.js` — see `docs/superpowers/specs/2026-05-27-frontend-redesign-design.md` for the design system.
```

- [ ] **Step 2: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass. If any fail unrelated to this redesign, investigate before continuing.

- [ ] **Step 3: Run a production build to catch purge issues**

```bash
npm run build
```

Expected: no warnings about missing classes; build completes. If a class you use is purged, add it to the `safelist` in `tailwind.config.js`.

- [ ] **Step 4: Manual smoke test — golden path**

In the browser, logged in as a real user:
1. `/` Dashboard — tiles animate in, numbers count up, sparkline draws, top office/item show.
2. `/receive` — fill the form, submit. Toast appears top-right after redirect.
3. `/release` — pick an item, set `qty > 50% available`, submit → confirm modal pops. Cancel, then set smaller qty → submits straight.
4. `/acknowledge` — click Acknowledge on a pending row, fill the modal, submit. Row animates out. Refresh: it now appears in the lower history table.
5. `/transactions` — apply each filter; clear works; click into a row → two-column show layout.
6. `/items` — card grid with sparklines; hover lifts a card; click into → show page with count-up + hero movement sparkline.
7. `/profile` — three bento cards; update name → "Saved." appears; delete-account button opens themed modal.
8. Log out, visit `/login` — split layout with brand panel on left.
9. In DevTools, set `prefers-reduced-motion: reduce` (Rendering tab → "Emulate CSS media feature prefers-reduced-motion"). Reload `/`. Expected: tiles appear instantly, numbers show final values immediately, no sparkline draw animation.

- [ ] **Step 5: Commit**

```bash
git add README.md
git commit -m "docs: add local dev section to README"
```

- [ ] **Step 6: Push the branch (if working on a feature branch)**

Skip if working directly on `main`.

```bash
git push -u origin HEAD
```

---

## Files Created (full list)

```
docs/superpowers/specs/2026-05-27-frontend-redesign-design.md   (already exists)
docs/superpowers/plans/2026-05-27-frontend-redesign.md          (this file)
resources/js/plugins/count-up.js
resources/js/plugins/stagger.js
resources/views/components/button.blade.php
resources/views/components/input.blade.php
resources/views/components/select.blade.php
resources/views/components/textarea.blade.php
resources/views/components/label.blade.php
resources/views/components/bento-card.blade.php
resources/views/components/page-header.blade.php
resources/views/components/status-badge.blade.php
resources/views/components/empty-state.blade.php
resources/views/components/table.blade.php
resources/views/components/table/row.blade.php
resources/views/components/toast.blade.php
resources/views/components/toast-container.blade.php
resources/views/components/stat-tile.blade.php
resources/views/components/sparkline.blade.php
tests/Feature/LayoutTest.php
tests/Feature/DashboardTest.php
tests/Feature/ItemIndexTest.php
```

## Files Modified

```
tailwind.config.js
package.json
composer.json
resources/css/app.css
resources/js/app.js
resources/views/layouts/app.blade.php
resources/views/layouts/guest.blade.php
resources/views/dashboard.blade.php
resources/views/receive.blade.php
resources/views/release.blade.php
resources/views/acknowledge.blade.php
resources/views/transactions.blade.php
resources/views/transactions-show.blade.php
resources/views/items.blade.php
resources/views/items-show.blade.php
resources/views/auth/login.blade.php
resources/views/auth/register.blade.php
resources/views/auth/forgot-password.blade.php
resources/views/auth/reset-password.blade.php
resources/views/auth/confirm-password.blade.php
resources/views/auth/verify-email.blade.php
resources/views/profile/edit.blade.php
resources/views/profile/partials/update-profile-information-form.blade.php
resources/views/profile/partials/update-password-form.blade.php
resources/views/profile/partials/delete-user-form.blade.php
app/Http/Controllers/DashboardController.php
app/Http/Controllers/ItemController.php
README.md
```
