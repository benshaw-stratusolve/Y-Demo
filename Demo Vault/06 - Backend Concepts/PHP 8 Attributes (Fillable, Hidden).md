# PHP 8 Attributes (Fillable, Hidden)

> Laravel 13's new syntax for declaring mass-assignment protection and JSON serialisation exclusions — using PHP 8 attributes instead of class property arrays.

---

## Concept Explained

Traditionally, mass-assignment protection in Eloquent was declared as a `protected $fillable = [...]` or `protected $guarded = [...]` array property. Laravel 13 introduces PHP 8 native attribute syntax (`#[Fillable([...])]`) that achieves the same result but is more explicit — it sits directly on the class declaration, making it immediately visible.

---

## How it's Used in Y

### `#[Fillable]` — controls which columns `create()` and `update()` can set

```php
// app/Models/User.php
#[Fillable(['name', 'email', 'password', 'avatar', 'username', 'bio', 'profanity_strikes'])]
class User extends Authenticatable ...
```

Columns deliberately **excluded** from fillable:
- `banned_at` — must be set via `forceFill()` to make the security intent explicit
- `is_admin` — same reason; admin actions explicitly call `forceFill(['is_admin' => true])`

```php
// app/Models/Post.php
#[Fillable(['user_id', 'body', 'parent_post_id', 'repost_of_id', 'image'])]

// app/Models/Follow.php
#[Fillable(['follower_id', 'following_id'])]

// app/Models/Like.php
#[Fillable(['user_id', 'post_id'])]
```

### `#[Hidden]` — excludes fields from JSON serialisation

```php
// app/Models/User.php
#[Hidden(['password', 'remember_token'])]
```

These fields are never included when the User model is converted to JSON (e.g. when passed as Inertia props). Without this, the hashed password would be sent to the browser.

### `forceFill()` — bypasses fillable protection intentionally

Used in admin actions where you explicitly need to set non-fillable fields:

```php
// app/Http/Controllers/PostController.php
$user->forceFill(['banned_at' => now()])->save();

// app/Filament/Resources/Users/UserResource.php
$record->forceFill(['is_admin' => ! $record->is_admin])->save();
```

---

## Key Code Snippet

```php
#[Fillable(['name', 'email', 'password', 'avatar', 'username', 'bio', 'profanity_strikes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
```

---

## Why This Approach

Keeping `banned_at` and `is_admin` out of `$fillable` is a security decision — it ensures that no form submission (even a malicious one that adds extra fields) can ever set these through a normal `User::create()` or `$user->update()` call. The use of `forceFill()` in admin-only code makes the intent clear: "I know this bypasses protection, and I'm doing it on purpose."

---

## Related Notes

- [[Users]]
- [[Profanity Strike + Ban System]]
- [[Eloquent Accessors + Casts]]
