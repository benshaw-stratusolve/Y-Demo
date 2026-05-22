# Stack Overview

> A complete list of every major technology in the Y project and why each one is here.

---

## Concept Explained

Y is a full-stack Twitter clone built on a modern Laravel monolith. All page rendering happens server-side (Laravel generates the data), all interactivity happens client-side (Svelte renders the UI), and Inertia.js bridges the two without building a separate API. Filament provides the admin panel. Fortify handles authentication plumbing.

---

## The Full Stack

| Layer | Package | Version | Role |
|---|---|---|---|
| Runtime | PHP | 8.4 | Server language |
| Framework | `laravel/framework` | 13 | HTTP routing, ORM, queues, mail |
| Auth | `laravel/fortify` | 1 | Registration, login, password reset, email verification |
| SPA bridge | `inertiajs/inertia-laravel` | 3 | Sends page props as JSON, manages browser history |
| Frontend | `@inertiajs/svelte` | 3 | Inertia adapter for Svelte |
| UI framework | Svelte | 5 | Reactive component system (Runes API) |
| Styling | Tailwind CSS | 4 | Utility-first CSS |
| Admin | `filament/filament` | 4 | Full admin panel with resources and widgets |
| Image processing | Intervention Image (via Laravel facade) | — | Resize/compress uploaded post images |
| AI | Google Gemini API | — | Powers the FlockAI chat feature |
| Testing | `pestphp/pest` | 4 | Test runner with expressive syntax |
| Code style | `laravel/pint` | 1 | PHP code formatter (runs `--dirty` after every edit) |
| Type-safe routes | `laravel/wayfinder` | 0 | Generates TypeScript functions for every Laravel route |
| Dev server | Laravel Herd | — | Local HTTPS at `https://y.test` |

---

## How it's Used in Y

- `app/` — all PHP: models, controllers, jobs, notifications, Filament resources
- `resources/js/` — all Svelte: pages, components, layouts, lib utilities
- `resources/js/actions/` — Wayfinder-generated TypeScript route helpers (auto-generated, never hand-written)
- `resources/js/routes/` — Wayfinder named-route helpers
- `database/migrations/` — 14 migrations defining the full schema

---

## Key Code Snippet

```ts
// resources/js/app.ts — Inertia bootstrap
createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    layout: (name) => {
        if (name.startsWith('auth/')) return AuthLayout;
        if (name.startsWith('settings/')) return DashboardSettingsLayout;
        return null; // Dashboard, Notifications etc. self-contain their layout
    },
    progress: { color: '#000000', includeCSS: false },
}).then(dismissSplash);
```

---

## Why This Approach

A Laravel + Inertia + Svelte stack gives you the productivity of a server-rendered framework (routing, ORM, validation all in PHP) with the UX of a SPA (no full page reloads, reactive UI) — without the complexity of maintaining two separate codebases and a REST API. Svelte 5 Runes bring a signal-based reactivity model that eliminates most boilerplate. Filament v4 delivers a production-quality admin panel in hours rather than weeks.

---

## Related Notes

- [[Laravel + Inertia + Svelte]]
- [[Wayfinder (Type-Safe Routes)]]
- [[Svelte 5 Runes]]
- [[Admin Overview]]
