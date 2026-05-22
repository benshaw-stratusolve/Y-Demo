# Wayfinder (Type-Safe Routes)

> A Laravel package that auto-generates TypeScript functions for every route and controller action — no hardcoded URL strings on the frontend.

---

## Concept Explained

Wayfinder reads your Laravel routes and generates TypeScript files that export a function for each controller action. Each function returns an object with `url` and `method` properties, typed exactly to the route's parameter requirements. If you rename a route or change its URL, the TypeScript type changes — no silent breakage from hardcoded strings.

---

## How it's Used in Y

### Generated action files (`resources/js/actions/`)

Generated automatically — never hand-edited. One file per controller, mirroring the PHP namespace:

```
resources/js/actions/
├── App/Http/Controllers/
│   ├── PostController.ts        ← store, like, reply, show, destroy, repost
│   ├── DashboardController.ts
│   ├── NotificationsController.ts
│   ├── Settings/ProfileController.ts
│   └── ...
└── Laravel/Fortify/Http/Controllers/
    └── AuthenticatedSessionController.ts  ← destroy (logout)
```

### Generated named-route files (`resources/js/routes/`)

For named routes accessed by name rather than controller:

```ts
// resources/js/routes/dashboard.ts (auto-generated)
export const dashboard = (): RouteDefinition<'get'> => ({ url: '/dashboard', method: 'get' })
```

### Using action functions in Svelte

```ts
import { store as storePost, like as likePost, destroy as destroyPost }
    from '@/actions/App/Http/Controllers/PostController';

// In an event handler:
router.post(storePost().url, data, { preserveScroll: true });
router.post(likePost(post.id).url, {}, { preserveScroll: true });
router.delete(destroyPost(post.id).url, {}, { preserveScroll: true });
```

For routes with parameters, the function requires them:

```ts
// TypeScript error if post.id is missing:
likePost(post.id)   // → { url: '/posts/42/like', method: 'post' }
likePost()          // TypeScript error: argument of type 'number' required
```

### Using with the `<Form>` component

```ts
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';

// ProfileController.update.form() returns { action: '/settings/profile', method: 'patch' }
<Form {...ProfileController.update.form()}>
```

The `.form()` helper formats the object for native HTML `<form>` attributes (`action` and `method`).

---

## Key Code Snippet

```ts
// Auto-generated: resources/js/actions/App/Http/Controllers/PostController.ts
export const like = (post: number | string, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: like.url(post, options),
    method: 'post',
})

like.url = (post: number | string, options?: RouteQueryOptions) => {
    return `/posts/${post}/like` + queryParams(options)
}
```

---

## Why This Approach

Without Wayfinder, frontend code looks like `router.post('/posts/' + post.id + '/like', ...)` — these strings are invisible to the type system and silently break when routes change. Wayfinder generates this boilerplate from the actual route definitions, so renaming a route in PHP immediately causes a TypeScript error in the frontend. It's the equivalent of `route()` helpers in Blade templates, but for the TypeScript/Svelte side.

---

## Related Notes

- [[Laravel + Inertia + Svelte]]
- [[Inertia Form Component]]
- [[Svelte 5 Runes]]
