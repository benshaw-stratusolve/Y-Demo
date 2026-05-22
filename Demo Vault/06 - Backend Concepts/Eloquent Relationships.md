# Eloquent Relationships

> The ORM relationship methods used across Y's models — what each one does and where each is used.

---

## Concept Explained

Eloquent relationships are methods on model classes that describe how models connect to each other. They return query builders that can be further constrained, eager loaded, or used with helpers like `toggle()`, `withCount()`, and `pluck()`. Lazy loading is disabled in non-production environments via `Model::preventLazyLoading()` in `AppServiceProvider`.

---

## Relationship Types in Y

### `hasMany` — one-to-many

```php
// User has many Posts
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}

// Post has many Likes
public function likes(): HasMany
{
    return $this->hasMany(Like::class);
}

// Post has many Replies (self-referential)
public function replies(): HasMany
{
    return $this->hasMany(Post::class, 'parent_post_id');
}
```

The second argument specifies the foreign key when it doesn't follow Laravel's convention (`{model}_id`).

### `belongsTo` — inverse of hasMany

```php
// Post belongs to User
public function user(): BelongsTo
{
    return $this->belongsTo(User::class)->withDefault([
        'name' => 'Deleted User',
        'username' => 'deleted',
    ]);
}
```

`withDefault()` returns a mock User object if the user has been deleted — prevents null-pointer crashes in Blade or Svelte.

### `belongsToMany` — many-to-many via pivot table

```php
// User follows many Users (via follows table)
public function followedUsers(): BelongsToMany
{
    return $this->belongsToMany(
        User::class, 'follows', 'follower_id', 'following_id'
    )->withTimestamps();
}

// User has liked many Posts (via likes table)
public function likedPosts(): BelongsToMany
{
    return $this->belongsToMany(Post::class, 'likes', 'user_id', 'post_id')
        ->withTimestamps();
}
```

`withTimestamps()` tells Eloquent to set `created_at`/`updated_at` on the pivot rows.

### `toggle()` on BelongsToMany

```php
$changes = auth()->user()->followedUsers()->toggle($user->id);
// Returns: ['attached' => [...ids], 'detached' => [...ids]]
```

Used for both follows and likes — attaches if the relationship doesn't exist, detaches if it does.

### `withCount()` — counting without loading

```php
Post::withCount(['likes', 'replies', 'reposts'])->get();
// Adds likes_count, replies_count, reposts_count to each Post
```

Runs a COUNT subquery for each relationship — no N+1.

---

## Key Code Snippet

```php
// DashboardController — efficient multi-relationship loading
$posts = Post::with(['user', 'replies.user'])   // eager load user and reply users
    ->withCount(['likes', 'replies', 'reposts']) // count without loading
    ->whereNull('parent_post_id')
    ->paginate(10);
```

---

## Why This Approach

`with()` (eager loading) prevents N+1 queries — without it, displaying 10 posts with authors would fire 11 queries (1 for posts + 10 for users). `withCount()` adds just a subquery for counts, avoiding loading entire collections just to count them. `preventLazyLoading()` in development ensures you catch N+1 issues immediately rather than in production.

---

## Related Notes

- [[Users]]
- [[Posts (replies + reposts)]]
- [[Follows]]
- [[Likes]]
