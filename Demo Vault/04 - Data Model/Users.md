# Users

> The central model of Y — every other model relates back to a user.

---

## Concept Explained

`App\Models\User` extends Laravel's `Authenticatable` base (which provides auth, password hashing, and remember-me token support). It uses PHP 8 attribute syntax for fillable/hidden field declarations, an Eloquent accessor for the computed `avatar_url`, and typed casts for security-sensitive and boolean fields.

---

## How it's Used in Y

File: `app/Models/User.php`

### Schema columns

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint | Primary key |
| `name` | string | Display name |
| `username` | string | @handle, unique, alphanumeric+underscore |
| `email` | string unique | Login credential |
| `password` | string | bcrypt hash |
| `avatar` | string nullable | Storage path e.g. `avatars/abc123.jpg` |
| `bio` | text nullable | Profile bio, max 500 chars |
| `profanity_strikes` | integer | 0–3, triggers ban at 3 |
| `banned_at` | timestamp nullable | Null = active, set = banned |
| `is_admin` | boolean | Admin panel access |
| `email_verified_at` | timestamp nullable | Email verification |

### Relationships defined

- `posts()` — `hasMany(Post::class)` — all posts by this user
- `likes()` — `hasMany(Like::class)` — all likes created by this user
- `follows()` — `hasMany(Follow::class, 'follower_id')` — who this user follows
- `followers()` — `hasMany(Follow::class, 'following_id')` — who follows this user
- `followedUsers()` — `belongsToMany(User, 'follows', 'follower_id', 'following_id')` — User objects this user follows
- `followerUsers()` — `belongsToMany(User, 'follows', 'following_id', 'follower_id')` — User objects that follow this user
- `likedPosts()` — `belongsToMany(Post, 'likes', 'user_id', 'post_id')` — Post objects this user has liked

### Key methods

- `isBanned(): bool` — checks `banned_at !== null`
- `canAccessPanel(Panel): bool` — Filament gate, returns `$this->is_admin === true`

---

## Key Code Snippet

```php
// app/Models/User.php
#[Fillable(['name', 'email', 'password', 'avatar', 'username', 'bio', 'profanity_strikes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    protected $appends = ['avatar_url'];

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->avatar
                ? Storage::disk('public')->url($this->avatar)
                : null
        );
    }

    protected function casts(): array
    {
        return [
            'password'  => 'hashed',   // auto-hashes on assignment
            'banned_at' => 'datetime',
            'is_admin'  => 'boolean',
        ];
    }
}
```

Note: `banned_at` and `is_admin` are intentionally **not** in `#[Fillable]` — admin actions use `forceFill()` instead to make the intent explicit.

---

## Why This Approach

Separating `avatar` (the storage path) from `avatar_url` (the computed full URL) keeps the model clean — the URL is always generated from the current storage configuration, not hardcoded. Using PHP 8 attribute syntax (`#[Fillable]`) is Laravel 13's preferred approach over the traditional `$fillable` array property.

---

## Related Notes

- [[PHP 8 Attributes (Fillable, Hidden)]]
- [[Eloquent Accessors + Casts]]
- [[Eloquent Relationships]]
- [[Avatar Upload]]
- [[Profanity Strike + Ban System]]
