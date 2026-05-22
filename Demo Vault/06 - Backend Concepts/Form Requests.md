# Form Requests

> Dedicated request classes that encapsulate validation logic and keep controllers clean.

---

## Concept Explained

A `FormRequest` is a Laravel class that handles both authorisation and validation for a specific HTTP request. Instead of calling `$request->validate([...])` inside the controller, you type-hint the `FormRequest` subclass and the validation runs automatically before the controller method is called. If validation fails, Laravel redirects back with errors without the controller ever executing.

---

## How it's Used in Y

### `ProfileUpdateRequest` (`app/Http/Requests/Settings/ProfileUpdateRequest.php`)

Uses the `ProfileValidationRules` trait and passes the current user's ID to `ignore()` so the unique check on `email` and `username` doesn't reject the user's own existing values:

```php
class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    public function rules(): array
    {
        return $this->profileRules($this->user()->id); // pass ID for ignore()
    }
}
```

### `PasswordUpdateRequest` (`app/Http/Requests/Settings/PasswordUpdateRequest.php`)

Validates both the current password and the new one. Includes a custom closure rule to prevent setting the same password:

```php
'password' => [
    ...$this->passwordRules(),
    function (string $attribute, mixed $value, \Closure $fail) {
        if (Hash::check($value, $this->user()->password)) {
            $fail('Your new password must be different from your current password.');
        }
    },
],
```

`Hash::check()` compares the new password against the stored bcrypt hash — if it matches, it's the same password.

### Validation traits

Two traits centralise shared rules to avoid duplication between registration and settings update:

- `app/Concerns/PasswordValidationRules` — `passwordRules()` (min 8, number, symbol, confirmed) and `currentPasswordRules()` (Laravel's `current_password` rule)
- `app/Concerns/ProfileValidationRules` — `profileRules(?int $userId)` which calls `nameRules()`, `usernameRules(?int $userId)`, `emailRules(?int $userId)`, and bio rules

---

## Key Code Snippet

```php
// app/Concerns/ProfileValidationRules.php
protected function usernameRules(?int $userId = null): array
{
    return [
        'required', 'string', 'max:30',
        'regex:/^[a-zA-Z0-9_]+$/',          // only letters, digits, underscore
        $userId === null
            ? Rule::unique(User::class)
            : Rule::unique(User::class)->ignore($userId),
    ];
}
```

---

## Why This Approach

Pulling rules into traits means `CreateNewUser` (registration) and `ProfileUpdateRequest` (settings) share identical validation logic. When password requirements change (e.g. "add uppercase requirement"), you update `PasswordValidationRules::passwordRules()` once and both contexts are updated. The `FormRequest` class itself stays tiny — just wiring up the trait.

---

## Related Notes

- [[Registration Flow]]
- [[Profile + Settings]]
- [[Service Classes (ProfanityService)]]
