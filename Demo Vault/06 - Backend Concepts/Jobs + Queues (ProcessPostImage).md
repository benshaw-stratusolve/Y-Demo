# Jobs + Queues (ProcessPostImage)

> How image processing is offloaded from the HTTP request cycle to a background worker using Laravel queues.

---

## Concept Explained

A Laravel job is a class that implements `ShouldQueue`, meaning it runs asynchronously in a background process rather than during the HTTP request. The job is serialised and pushed onto a queue (database, Redis, etc.); a queue worker picks it up and runs it independently. The HTTP request completes immediately — the user doesn't wait for image processing.

---

## How it's Used in Y

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

## Key Code Snippet

```php
Image::read($path)
    ->scaleDown(width: 1200)
    ->toJpeg(quality: 80)
    ->save($path);
```

---

## Why This Approach

Image processing (reading, resizing, re-encoding) can take 100–500ms or more for large files. Doing this synchronously during the HTTP request would make post creation feel slow. Offloading to a queue keeps the response fast. The existence check at the start of `handle()` handles race conditions where the post (and image) might be deleted before the job runs.

---

## Related Notes

- [[Intervention Image]]
- [[Posts (replies + reposts)]]
- [[Events + Listeners]]
