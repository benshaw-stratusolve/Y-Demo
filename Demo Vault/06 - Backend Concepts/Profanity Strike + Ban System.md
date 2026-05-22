# Profanity Strike + Ban System

> A three-strike system that issues warnings, then permanently bans users who repeatedly post inappropriate content.

---

## Concept Explained

When a post or reply contains profanity, instead of silently rejecting it, Y issues a "strike" — incrementing a counter on the user. At 3 strikes, the user's account is permanently banned (`banned_at` is set). Each violation sends a database notification with a custom message. Banned users can still log in but see a fullscreen ban overlay and cannot post.

---

## How it's Used in Y

File: `app/Http/Controllers/PostController.php` — `handleStrike()` private method

### The strike flow

```php
private function handleStrike(User $user, string $message): RedirectResponse
{
    $user->increment('profanity_strikes'); // atomic DB increment
    $user->refresh();                       // reload fresh from DB

    if ($user->profanity_strikes >= 3) {
        $user->forceFill(['banned_at' => now()])->save();

        $banMessage = 'Your account has been banned for repeated use of inappropriate language.';
        $user->notify(new ProfanityStrikeNotification($banMessage, isBan: true));

        return back()->withErrors(['body' => $banMessage]);
    }

    $remaining = 3 - $user->profanity_strikes;
    $strikeMessage = "{$message} Strike {$user->profanity_strikes} of 3 — {$remaining} remaining before your account is banned.";
    $user->notify(new ProfanityStrikeNotification($strikeMessage));

    return back()->withErrors(['body' => $strikeMessage]);
}
```

`increment()` is an atomic SQL `UPDATE users SET profanity_strikes = profanity_strikes + 1` — safe under concurrent requests. `refresh()` reloads the model from the database so `$user->profanity_strikes` reflects the actual stored value (not the in-memory stale value).

### Notification types

`ProfanityStrikeNotification` accepts an `$isBan` flag:

```php
public function toArray(object $notifiable): array
{
    return [
        'type'    => $this->isBan ? 'ban' : 'profanity_strike',
        'message' => $this->message,
    ];
}
```

The `ban` type renders differently in the Notifications page (styled in red with a ban icon).

### Ban check on post/reply submission

```php
public function store(Request $request, ProfanityService $profanity): RedirectResponse
{
    if ($user->isBanned()) {
        return back()->withErrors(['body' => 'Your account has been banned for repeated violations.']);
    }
    // ...
}
```

Banned users get a validation error if they somehow attempt to post (the frontend also shows a fullscreen overlay).

### Admin actions also use `forceFill`

Admins can ban/unban and reset strikes directly from the Filament panel:

```php
// app/Filament/Resources/Users/UserResource.php
Action::make('ban')->action(function (User $record, Component $livewire) {
    $record->forceFill(['banned_at' => now()])->save();
    // ...
});

Action::make('reset_strikes')->action(function (User $record) {
    $record->update(['profanity_strikes' => 0]); // profanity_strikes IS fillable
});
```

---

## Key Code Snippet

```php
$user->increment('profanity_strikes'); // atomic — no race condition
$user->refresh();
if ($user->profanity_strikes >= 3) {
    $user->forceFill(['banned_at' => now()])->save();
}
```

---

## Why This Approach

Using `increment()` rather than `$user->profanity_strikes++; $user->save()` is critical — multiple concurrent requests could read the same value and both increment to 2 instead of 2 and 3. `forceFill()` for `banned_at` is intentional: it bypasses fillable protection, making it explicit that this is a privileged operation not reachable via a form submission.

---

## Related Notes

- [[Service Classes (ProfanityService)]]
- [[PHP 8 Attributes (Fillable, Hidden)]]
- [[Laravel Notifications]]
- [[Users]]
- [[Admin Overview]]
