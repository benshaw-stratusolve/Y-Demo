# Jobs + Queues (ProcessPostImage)

> How background work is offloaded from the HTTP request cycle using Laravel queues — covering image processing and real-time broadcasts.

---

## Concept Explained

A Laravel job is a class that implements `ShouldQueue`, meaning it runs asynchronously in a background process rather than during the HTTP request. The job is serialised and pushed onto a queue (database, Redis, etc.); a queue worker picks it up and runs it independently. The HTTP request completes immediately — the user doesn't wait.

---

## Queue Configuration in Y

```dotenv
# .env
QUEUE_CONNECTION=database
```

Jobs are stored in the `jobs` database table and processed by the queue worker included in `composer run dev`:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

> **Note:** `QUEUE_CONNECTION` was originally `sync` (runs jobs inline in the HTTP request). It was changed to `database` after adding WebSocket broadcast jobs — with `sync`, a failed Reverb connection would return a 500 error to the user. With `database`, broadcast failures are isolated to the queue worker and don't affect HTTP responses.

---

## ProcessPostImage

File: `app/Jobs/ProcessPostImage.php`

### The job

```php
class ProcessPostImage implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $imagePath) {}

    public function handle(): void
    {
        if (! Storage::disk('public')->exists($this->imagePath)) {
            return; // file was deleted before the job ran
        }

        $path = Storage::disk('public')->path($this->imagePath);

        try {
            Image::read($path)
                ->scaleDown(width: 1200) // never upscale, only downscale
                ->toJpeg(quality: 80)    // convert to JPEG at 80% quality
                ->save($path);           // overwrite the original
        } catch (\Throwable $e) {
            Log::warning('Failed to process post image', [
                'path'  => $this->imagePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

### Dispatching the job

```php
// app/Http/Controllers/PostController.php
$image = $request->file('image')->store('post-images', 'public');

$post = $user->posts()->create([
    'body'  => $request->body,
    'image' => $image,
]);

if ($image) {
    ProcessPostImage::dispatch($image); // pushed to queue
}
```

The post is saved with the original uploaded image path immediately. The job later overwrites it with the compressed version. Users see the original briefly if they're very fast — in practice they won't notice.

### What the image processing does

1. `scaleDown(width: 1200)` — reduces width to max 1200px if larger; never upscales smaller images
2. `toJpeg(quality: 80)` — converts any format (PNG, WebP, GIF) to JPEG at 80% quality
3. `save($path)` — overwrites the uploaded file at the same path

---

## Queued Jobs in Y

| Job / Notification | What it does |
|---|---|
| `ProcessPostImage` | Resize + compress uploaded post image |
| `BroadcastEvent` (auto) | Pushes `PostBroadcast` / `PostInteractionUpdated` to Reverb |
| `SendQueuedNotifications` (auto) | Delivers `LikeNotification`, `FollowNotification`, etc. to DB + Reverb |

---

## Key Code Snippet

```php
Image::read($path)
    ->scaleDown(width: 1200)
    ->toJpeg(quality: 80)
    ->save($path);
```

---

## Why This Approach

Image processing (reading, resizing, re-encoding) can take 100–500ms or more for large files. Doing this synchronously during the HTTP request would make post creation feel slow. Offloading to a queue keeps the response fast. The existence check at the start of `handle()` handles race conditions where the post (and image) might be deleted before the job runs. The `database` queue driver needs no extra infrastructure (Redis, etc.) — perfect for a local demo app.

---

## Related Notes

- [[Intervention Image]]
- [[Laravel Reverb (WebSockets)]]
- [[Posts (replies + reposts)]]
- [[Events + Listeners]]
