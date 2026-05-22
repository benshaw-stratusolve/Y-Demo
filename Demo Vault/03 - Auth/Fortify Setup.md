# Fortify Setup

> Laravel Fortify is the headless authentication backend — it provides all the auth routes and logic but has no opinions about your UI.

---

## Concept Explained

Fortify ships pre-built controllers for login, registration, password reset, email verification, and password confirmation. It registers its own routes automatically. Because Y uses Inertia + Svelte (not Blade), the Fortify views are overridden to render Inertia responses instead of Blade templates. Fortify's action classes (like `CreateNewUser`) are swapped in `FortifyServiceProvider` to add project-specific logic.

---

## How it's Used in Y

File: `app/Providers/FortifyServiceProvider.php`

Three groups of configuration:

**1. Actions** — replace Fortify's default user creation and password reset with Y's custom implementations:
```php
Fortify::createUsersUsing(CreateNewUser::class);
Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
```

**2. Views** — each auth page renders an Inertia component instead of Blade:
```php
Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
    'canResetPassword' => Features::enabled(Features::resetPasswords()),
    'status' => $request->session()->get('status'),
]));
```

**3. Rate limiting** — login is rate-limited at 5 attempts/minute in production, 100/minute locally:
```php
RateLimiter::for('login', function (Request $request) {
    $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());
    return app()->environment('local')
        ? Limit::perMinute(100)->by($throttleKey)
        : Limit::perMinute(5)->by($throttleKey);
});
```

---

## Key Code Snippet

```php
// All six Fortify view overrides in FortifyServiceProvider::configureViews()
Fortify::registerView(fn () => Inertia::render('auth/Register', [
    'passwordRules' => Password::defaults()->toPasswordRulesString(),
]));

Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
    'status' => $request->session()->get('status'),
]));
```

---

## Why This Approach

Fortify handles the hard parts of auth (secure token hashing for password resets, email verification link signing, CSRF protection) without dictating the UI. You keep full control of every page's look and feel in Svelte while the security-critical server logic stays in battle-tested Fortify code.

---

## Related Notes

- [[Registration Flow]]
- [[Login + Custom Response]]
- [[Email Verification]]
- [[Form Requests]]
