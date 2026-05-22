# Email Verification

> How Y ensures users confirm their email address before accessing protected routes.

---

## Concept Explained

Laravel's email verification system works via the `MustVerifyEmail` contract on the User model and the `verified` middleware on routes. When verification is required but not completed, the user is redirected to `auth/VerifyEmail` (rendered by Fortify via Inertia). A signed URL is emailed to the user; clicking it sets `email_verified_at` and grants access.

---

## How it's Used in Y

### User model

The `User` class currently has `MustVerifyEmail` commented out:

```php
// use Illuminate\Contracts\Auth\MustVerifyEmail;
class User extends Authenticatable // implements MustVerifyEmail
```

This means email verification is defined and ready but not enforced. The infrastructure is complete — Fortify's `verifyEmailView` renders `auth/VerifyEmail.svelte`, and the resend route exists.

### Protected routes use `verified` middleware

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);
    // ... all main app routes
});
```

When `MustVerifyEmail` is enabled on the User model, any user hitting these routes without a verified email is redirected to the verification notice page.

### Profile settings reveal the verification state

```php
// app/Http/Controllers/Settings/ProfileController.php
return Inertia::render('settings/Profile', [
    'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
    // ...
]);
```

The Profile settings page shows an "email not verified" banner when this prop is true.

---

## Key Code Snippet

```php
// app/Providers/FortifyServiceProvider.php
Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
    'status' => $request->session()->get('status'),
]));
```

---

## Why This Approach

Keeping `MustVerifyEmail` commented out during development avoids friction in local testing (no need to check email for every factory-created user). The `verified` middleware is still on all routes, so enabling verification is a one-line uncomment — the entire flow is already wired up.

---

## Related Notes

- [[Fortify Setup]]
- [[Registration Flow]]
- [[Profile + Settings]]
