# Login + Custom Response

> How Y customises Fortify's login response to show a welcome-back toast and block banned users.

---

## Concept Explained

Fortify dispatches a `LoginResponse` contract after successful authentication. By default it simply redirects to `config('fortify.home')`. Y replaces this binding in the service container with an anonymous class that adds a flash toast and skips the toast for banned users (who are shown a ban overlay on the dashboard instead).

---

## How it's Used in Y

File: `app/Providers/AppServiceProvider.php` — `register()` method

The custom response is registered as a singleton that replaces Fortify's default:

```php
$this->app->instance(LoginResponseContract::class, new class implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        if (! auth()->user()->isBanned()) {
            Inertia::flash('toast', [
                'type'        => 'success',
                'title'       => 'Welcome back, '.auth()->user()->name.'!',
                'description' => "You're now signed in to Y.",
            ]);
        }

        return redirect()->intended(config('fortify.home'));
    }
});
```

`Inertia::flash()` stores the toast data in the session. On the next Inertia response, it's included as a `flash` property. `initializeFlashToast()` in `resources/js/lib/flash-toast.ts` listens for Inertia's `flash` event and calls `notifications.add()` to show the toast.

---

## Key Code Snippet

```ts
// resources/js/lib/flash-toast.ts
export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const data = (event as CustomEvent).detail?.flash?.toast;
        if (!data) return;
        notifications.add({
            type: data.type,
            title: data.title ?? data.message ?? '',
            description: data.description,
        });
    });
}
```

---

## Why This Approach

Overriding the `LoginResponse` binding in `register()` (not `boot()`) ensures it replaces Fortify's binding before Fortify registers its own. Using `Inertia::flash()` rather than a Laravel session flash means the data survives the redirect and is automatically cleaned up by Inertia — no manual session cleanup needed.

---

## Related Notes

- [[Fortify Setup]]
- [[Notification Sounds]]
- [[Shared Data (Inertia Middleware)]]
- [[Profanity Strike + Ban System]]
