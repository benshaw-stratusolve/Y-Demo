# Eloquent Accessors + Casts

> How models expose computed properties and automatically transform stored values.

---

## Concept Explained

**Accessors** are computed model attributes defined as protected methods returning an `Attribute` object. They run every time you access the property and are not stored in the database. **Casts** automatically convert database values to PHP types on read and back to storable values on write — defined in the `casts()` method.

---

## How it's Used in Y

### Accessors

**`User::avatarUrl()`** — converts a storage path to a full public URL:

```php
// app/Models/User.php
protected $appends = ['avatar_url'];

protected function avatarUrl(): Attribute
{
    return Attribute::get(
        fn () => $this->avatar
            ? Storage::disk('public')->url($this->avatar)
            : null
    );
}
```

`$appends` tells Eloquent to include `avatar_url` when the model is serialised to JSON (which happens when Inertia passes it as props). Without `$appends`, the accessor works when called in PHP but won't appear in the JSON output to the frontend.

**`Post::imageUrl()`** — same pattern for post images:

```php
// app/Models/Post.php
protected $appends = ['image_url'];

protected function imageUrl(): Attribute
{
    return Attribute::get(
        fn () => $this->image
            ? Storage::disk('public')->url($this->image)
            : null
    );
}
```

### Casts

```php
// app/Models/User.php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',   // Carbon instance on read
        'password'          => 'hashed',      // auto-bcrypt on write
        'banned_at'         => 'datetime',    // Carbon instance on read
        'is_admin'          => 'boolean',     // int 0/1 → true/false
    ];
}
```

`'hashed'` cast is particularly important — it means `$user->password = 'plain'` automatically bcrypts before saving. You never need to call `Hash::make()` manually.

`'datetime'` cast means you can call Carbon methods directly: `$user->banned_at->diffForHumans()`.

---

## Key Code Snippet

```php
// The 'hashed' cast in action — no Hash::make() needed
$user = User::create([
    'password' => $input['password'], // plain text here
]);
// Stored in DB as: $2y$12$... (bcrypt hash)
```

---

## Why This Approach

Separating `avatar` (path) from `avatar_url` (full URL) via an accessor means the URL generation is centralised. If the storage driver changes from local disk to S3, only the `Storage::disk('public')->url()` call needs updating — the accessor handles the rest automatically for every part of the app that references `avatar_url`.

---

## Related Notes

- [[Users]]
- [[Posts (replies + reposts)]]
- [[PHP 8 Attributes (Fillable, Hidden)]]
- [[Avatar Upload]]
