# Posts (replies + reposts)

> One table handles original posts, replies, and reposts through two nullable self-referential foreign keys.

---

## Concept Explained

The `posts` table uses a single-table pattern for three content types. `parent_post_id` makes a post a reply to another post. `repost_of_id` makes a post a repost (without its own body — `body` is null). Original posts have both columns null. This means all filtering throughout the app must explicitly exclude replies and reposts where needed.

---

## How it's Used in Y

File: `app/Models/Post.php`

### Schema columns

| Column | Type | Purpose |
|---|---|---|
| `id` | bigint | Primary key |
| `user_id` | FK → users | Author |
| `body` | text nullable | Post text (null for pure reposts) |
| `parent_post_id` | FK → posts nullable | Set when this is a reply |
| `repost_of_id` | FK → posts nullable | Set when this is a repost |
| `image` | string nullable | Storage path for attached image |

Indexes on both `parent_post_id` and `repost_of_id` for fast feed queries.

### Post types distinguished by query filters

```php
// Original posts only:
->whereNull('parent_post_id')->whereNull('repost_of_id')->whereNotNull('body')

// Replies only:
->whereNotNull('parent_post_id')

// Reposts only:
->whereNotNull('repost_of_id')
```

### Relationships

- `user()` — `belongsTo(User)` with `withDefault(['name' => 'Deleted User', 'username' => 'deleted'])` — graceful handling of deleted accounts
- `parent()` — `belongsTo(Post, 'parent_post_id')` — the post being replied to
- `replies()` — `hasMany(Post, 'parent_post_id')` — all replies to this post
- `repostOf()` — `belongsTo(Post, 'repost_of_id')` — the original post being reposted
- `reposts()` — `hasMany(Post, 'repost_of_id')` — all reposts of this post
- `likes()` — `hasMany(Like)` — likes on this post

### Accessor

`imageUrl()` computes the full public URL from the `image` storage path, appended as `image_url`.

---

## Key Code Snippet

```php
// app/Models/Post.php
#[Fillable(['user_id', 'body', 'parent_post_id', 'repost_of_id', 'image'])]
class Post extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Deleted User',
            'username' => 'deleted',
        ]);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Post::class, 'parent_post_id');
    }

    public function repostOf(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'repost_of_id');
    }
}
```

---

## Why This Approach

A single table keeps queries simple and avoids complex joins between separate tables. The `withDefault()` on the user relationship prevents null-pointer errors if a user deletes their account while their posts still exist — the post gracefully shows "Deleted User" instead of crashing.

---

## Related Notes

- [[Eloquent Relationships]]
- [[Likes]]
- [[Eloquent Accessors + Casts]]
- [[Jobs + Queues (ProcessPostImage)]]
