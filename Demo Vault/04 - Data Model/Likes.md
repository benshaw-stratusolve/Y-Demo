# Likes

> A pivot table that records which user liked which post, with optimistic UI on the frontend.

---

## Concept Explained

`likes` is a simple pivot between `users` and `posts`. Toggling a like uses `BelongsToMany::toggle()` which returns the changed IDs in `attached` and `detached` arrays — allowing the controller to know whether to send a notification (only on new likes, not on unlikes).

---

## How it's Used in Y

File: `app/Models/Like.php`, relationships on `User` and `Post`

### Schema

```
likes
├── id
├── user_id → users.id (cascade delete)
├── post_id → posts.id (cascade delete)
└── timestamps
```

### Toggle in PostController

```php
// app/Http/Controllers/PostController.php
public function like(Post $post): RedirectResponse
{
    $user = auth()->user();
    $changes = auth()->user()->likedPosts()->toggle($post->id);

    if (! empty($changes['attached']) && $post->user_id !== $user->id) {
        $post->user->notify(new LikeNotification($user, Str::limit($post->body, 50)));
    }

    return back();
}
```

Notification is skipped when you like your own post (`$post->user_id !== $user->id`).

### Dashboard — efficient liked-by-user lookup

Rather than loading `likes` for every post individually (N+1), the dashboard loads all liked post IDs in one query and uses a hash-map lookup:

```php
$likedIds = Like::where('user_id', $user->id)
    ->whereIn('post_id', $postIds)
    ->pluck('post_id')
    ->flip()   // [postId => 0, ...] — O(1) isset()
    ->all();

$posts->through(function (Post $post) use ($likedIds) {
    $post->liked_by_user = isset($likedIds[$post->id]);
    return $post;
});
```

### Optimistic UI on the frontend

```ts
// resources/js/pages/Dashboard.svelte
function toggleLike(post: any) {
    const liked = localLikes[post.id]?.liked ?? post.liked_by_user;
    const count = localLikes[post.id]?.count ?? post.likes_count;
    localLikes[post.id] = { liked: !liked, count: count + (liked ? -1 : 1) };

    router.post(likePost(post.id).url, {}, {
        preserveScroll: true,
        preserveState: true,
        onError: () => { delete localLikes[post.id]; }, // rollback on error
    });
}
```

The heart animates instantly; if the server rejects it, the local state rolls back.

---

## Key Code Snippet

```php
// app/Models/User.php
public function likedPosts(): BelongsToMany
{
    return $this->belongsToMany(Post::class, 'likes', 'user_id', 'post_id')
        ->withTimestamps();
}
```

---

## Why This Approach

`flip()` on the plucked IDs collection turns a linear array into a hash-map, making the per-post check `isset()` — O(1) instead of O(n). This avoids an N+1 problem where a page of 10 posts would otherwise fire 10 separate "did user like this post?" queries.

---

## Related Notes

- [[Posts (replies + reposts)]]
- [[Eloquent Relationships]]
- [[Laravel Notifications]]
