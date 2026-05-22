# Profile + Settings

> Three settings pages (Profile, Security, Appearance) backed by dedicated controllers, form requests, and throttled routes.

---

## Concept Explained

Settings are split across two controllers. `ProfileController` manages name/username/email/bio/avatar. `SecurityController` manages the password. Each form uses a `FormRequest` subclass for validation. The settings layout (`resources/js/layouts/settings/Layout.svelte`) wraps all three pages with a shared sidebar nav.

---

## How it's Used in Y

### Routes (`routes/settings.php`)

```php
Route::middleware(['auth'])->group(function () {
    Route::get('settings/profile',  [ProfileController::class, 'edit']);
    Route::patch('settings/profile', [ProfileController::class, 'update']);
    Route::post('settings/profile/avatar',   [ProfileController::class, 'updateAvatar']);
    Route::delete('settings/profile/avatar', [ProfileController::class, 'destroyAvatar']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy']);
    Route::get('settings/security',  [SecurityController::class, 'edit']);
    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1');  // 6 attempts per minute
});
```

Password update requires `verified` middleware and is throttled at 6/minute.

### Profile update flow

1. `ProfileUpdateRequest` validates the form (reusing `ProfileValidationRules` trait, passing the current user's ID to `Rule::unique()->ignore()` so the unique check ignores their own record)
2. `ProfanityService` is injected and checks the bio — returns a validation error if it contains banned words
3. If email changed, `email_verified_at` is nulled out (forcing re-verification)
4. `Inertia::flash('toast', [...])` queues a success toast for the next page load

### Password update flow (`SecurityController::update`)

```php
public function update(PasswordUpdateRequest $request): RedirectResponse
{
    $request->user()->update(['password' => $request->password]);
    $request->user()->notify(new PasswordUpdateNotification);
    Inertia::flash('toast', ['type' => 'success', 'message' => 'Password updated.']);
    return back();
}
```

`PasswordUpdateRequest` validates `current_password` using Laravel's `current_password` rule, validates the new password against `Password::defaults()`, and uses a closure to reject the same password being set again.

---

## Key Code Snippet

```php
// app/Http/Requests/Settings/PasswordUpdateRequest.php
public function rules(): array
{
    return [
        'current_password' => $this->currentPasswordRules(),
        'password' => [
            ...$this->passwordRules(),
            function (string $attribute, mixed $value, \Closure $fail) {
                if (Hash::check($value, $this->user()->password)) {
                    $fail('Your new password must be different from your current password.');
                }
            },
        ],
    ];
}
```

---

## Why This Approach

Shared validation traits (`PasswordValidationRules`, `ProfileValidationRules`) prevent duplication between the registration action and the settings update request — the same rules apply in both contexts. The `ignore()` on the unique rule is essential: without it, a user saving their profile without changing their email would fail the unique validation because their email already exists in the database (under their own record).

---

## Related Notes

- [[Form Requests]]
- [[Avatar Upload]]
- [[Email Verification]]
- [[Laravel Notifications]]
- [[Service Classes (ProfanityService)]]
