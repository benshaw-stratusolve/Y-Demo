# Avatar Upload

> How users upload, preview, replace, and delete their profile avatar — from the Svelte component to disk storage.

---

## Concept Explained

Avatar upload is a dedicated route (separate from profile update) that accepts a single image file, validates its size, deletes the old avatar from disk, stores the new one to the `public` storage disk, and saves just the path on the User model. The `avatar_url` accessor then computes the full URL on demand.

---

## How it's Used in Y

### Backend (`app/Http/Controllers/Settings/ProfileController.php`)

```php
public function updateAvatar(Request $request): RedirectResponse
{
    $request->validate(['avatar' => ['required', 'image', 'max:5120']]); // 5MB

    $user = $request->user();

    if ($user->avatar) {
        Storage::disk('public')->delete($user->avatar);  // clean up old file
    }

    $user->avatar = $request->file('avatar')->store('avatars', 'public');
    $user->save();

    Inertia::flash('toast', ['type' => 'success', 'message' => 'Avatar updated.']);

    return to_route('profile.edit');
}
```

`->store('avatars', 'public')` stores the file in `storage/app/public/avatars/` with a random filename, and returns the relative path (e.g. `avatars/xK3mQ.jpg`). That path is what's saved to the database — the full URL is always computed by the accessor.

### Frontend component (`resources/js/components/AvatarUpload.svelte`)

Key behaviours:
- **Preview before upload** — `URL.createObjectURL(file)` shows the image locally before any server round-trip
- **Size check client-side** — rejects files over 5MB before the upload request
- **Fallback avatar** — initials + colour generated from username hash when no avatar is set

```ts
const avatarColors = ['bg-red-400', 'bg-orange-400', ...];
const avatarBg = $derived(
    avatarColors[userName.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0) % avatarColors.length]
);
```

The same deterministic colour algorithm is used server-side in `HasAvatarFallback` trait for the Filament admin panel and `ui-avatars.com` fallback URLs.

### Delete avatar

```php
public function destroyAvatar(Request $request): RedirectResponse
{
    if ($user->avatar) {
        Storage::disk('public')->delete($user->avatar);
        $user->avatar = null;
        $user->save();
    }
    // ...
}
```

---

## Key Code Snippet

```php
// app/Models/User.php — avatar_url accessor
protected function avatarUrl(): Attribute
{
    return Attribute::get(
        fn () => $this->avatar
            ? Storage::disk('public')->url($this->avatar)
            : null
    );
}
```

---

## Why This Approach

Storing only the file path (not the full URL) in the database means storage configuration changes (e.g. moving from local to S3) only require changing the disk driver — no data migration needed. The `public` disk writes to `storage/app/public/` and serves files via the `storage/` symlink created by `php artisan storage:link`.

---

## Related Notes

- [[Users]]
- [[Eloquent Accessors + Casts]]
- [[Profile + Settings]]
