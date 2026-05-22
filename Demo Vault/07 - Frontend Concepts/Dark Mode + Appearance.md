# Dark Mode + Appearance

> How Y persists the user's light/dark/system theme preference across page loads using cookies and localStorage.

---

## Concept Explained

Y supports three appearance modes: `light`, `dark`, and `system` (follows OS preference). The preference is stored in both localStorage (for fast client-side access) and a cookie (so the server can read it on first load and apply the `dark` class to the `<html>` tag before any JavaScript runs — preventing a flash of the wrong theme).

---

## How it's Used in Y

### Server side: `HandleAppearance` middleware

```php
// app/Http/Middleware/HandleAppearance.php
public function handle(Request $request, Closure $next): Response
{
    View::share('appearance', $request->cookie('appearance') ?? 'system');
    return $next($request);
}
```

Shares the appearance value with the Blade view.

### Blade template applies the class before JS loads

```html
<!-- resources/views/app.blade.php -->
<html @class(['dark' => ($appearance ?? 'system') == 'dark'])>
```

If the cookie says `dark`, the `<html>` tag gets the `dark` class immediately — Tailwind's `dark:` variants activate with no flash.

### Client side: `resources/js/lib/theme.svelte.ts`

```ts
export function initializeTheme(): void {
    appearance.value = getStoredAppearance(); // reads localStorage
    applyTheme(appearance.value);              // adds/removes 'dark' class
    // watches for system preference changes
    themeChangeMediaQuery.addEventListener('change', handleSystemThemeChange);
}

export function updateAppearance(value: Appearance): void {
    appearance.value = value;
    localStorage.setItem('appearance', value);
    setCookie('appearance', value, 365);       // 1 year cookie
    applyTheme(value);
}
```

`initializeTheme()` is called at the very top of `app.ts`, before `createInertiaApp()` — so the theme is applied before Svelte renders anything.

### `system` mode

```ts
const prefersDark = (): boolean =>
    window.matchMedia('(prefers-color-scheme: dark)').matches;

const isDarkMode = (value: Appearance): boolean =>
    value === 'dark' || (value === 'system' && prefersDark());
```

`system` mode listens to `prefers-color-scheme` media query changes — if you switch OS dark mode while the page is open, the theme updates instantly.

### Appearance settings page

`resources/js/pages/settings/Appearance.svelte` provides three buttons. Clicking one calls `updateAppearance('light')` etc. — no server round-trip needed.

---

## Key Code Snippet

```ts
// Called once at app bootstrap — before createInertiaApp()
initializeTheme();

// On preference change from settings page:
updateAppearance('dark');
// → updates localStorage + cookie + immediately applies dark class
```

---

## Why This Approach

Using both localStorage and a cookie is the key design decision. localStorage is fast (synchronous read) and used by the JS theme initialiser to apply the theme immediately on page load. The cookie allows the server to include the `dark` class in the first HTML response — eliminating the white flash that would occur if dark mode was only applied after JavaScript runs. One mechanism alone isn't sufficient.

---

## Related Notes

- [[shadcn-svelte Components]]
- [[Svelte 5 Runes]]
- [[Request Lifecycle]]
