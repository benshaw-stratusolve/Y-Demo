# Follows

> A pivot table that records who follows whom, enabling the "Following" feed and follow notifications.

---

## Concept Explained

The `follows` table is a pivot (join table) between users: `follower_id` is the person doing the following, `following_id` is the person being followed. Eloquent exposes this through both a `HasMany` relationship (for raw Follow records) and a `BelongsToMany` relationship (for querying User objects directly).

---

## How it's Used in Y

File: `app/Models/Follow.php` and relationships on `app/Models/User.php`

### Schema

```
follows
├── id
├── follower_id  → users.id (cascade delete)
├── following_id → users.id (cascade delete)
└── timestamps
```

### Two relationship styles on User

**HasMany** (gives Follow records, useful for plucking IDs efficiently):
```php
public function follows(): HasMany
{
    return $this->hasMany(Follow::class, 'follower_id');
}
```

**BelongsToMany** (gives User objects, useful for `toggle()`):
```php
public function followedUsers(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
        ->withTimestamps();
}
```

### Toggle follow in `FollowController`

```php
// app/Http/Controllers/FollowController.php
$changes = auth()->user()->followedUsers()->toggle($user->id);

if (! empty($changes['attached'])) {
    $user->notify(new FollowNotification(auth()->user()));
}
```

`toggle()` attaches the pivot row if it doesn't exist, detaches it if it does — one method handles both follow and unfollow.

### Dashboard feed filtering

```php
$followingIds = $user->follows()->pluck('following_id')->push($user->id);
$postsQuery->whereIn('user_id', $followingIds); // show only followed users' posts
```

---

## Key Code Snippet

```php
// app/Models/User.php
public function followedUsers(): BelongsToMany
{
    return $this->belongsToMany(
        User::class, 'follows', 'follower_id', 'following_id'
    )->withTimestamps();
}

public function followerUsers(): BelongsToMany
{
    return $this->belongsToMany(
        User::class, 'follows', 'following_id', 'follower_id'
    )->withTimestamps();
}
```

---

## Why This Approach

Using `BelongsToMany` with `toggle()` keeps the follow/unfollow action to a single database call. The `HasMany` alternative (`follows()`) is used where you only need IDs (e.g. building `$followingIds` for the feed) — it avoids loading full User objects unnecessarily.

---

## Related Notes

- [[Eloquent Relationships]]
- [[Users]]
- [[Laravel Notifications]]
