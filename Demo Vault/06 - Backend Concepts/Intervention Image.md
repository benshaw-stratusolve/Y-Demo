# Intervention Image

> The PHP image manipulation library used to resize and compress post images after upload.

---

## Concept Explained

Intervention Image is a PHP library that provides a fluent API for reading, transforming, and saving images. The Laravel facade (`Intervention\Image\Laravel\Facades\Image`) integrates it with Laravel's config system for driver selection (GD vs Imagick). In Y, it's used exclusively in the `ProcessPostImage` job to normalise uploaded images.

---

## How it's Used in Y

File: `app/Jobs/ProcessPostImage.php`

### The processing chain

```php
Image::read($path)          // load the image from disk
    ->scaleDown(width: 1200) // resize if wider than 1200px; never upscale
    ->toJpeg(quality: 80)    // convert to JPEG at 80% quality
    ->save($path);           // overwrite the file in place
```

**`scaleDown(width: 1200)`** — unlike `resize()`, `scaleDown()` only reduces dimensions, never increases them. An uploaded 400×400px image is left at 400×400px; a 3000×2000px image is scaled to 1200×800px maintaining aspect ratio.

**`toJpeg(quality: 80)`** — converts any format (PNG, WebP, HEIC, GIF) to JPEG. Quality 80 is the standard trade-off — visually lossless for social media display but significantly smaller than quality 100.

**`->save($path)`** — overwrites the original uploaded file. No new filename is generated — the path stored in the database remains valid.

### Why it runs in a job

Image processing happens in `ProcessPostImage` (a queued job), not synchronously in the controller. The post is saved with the original file immediately after upload. The job runs in the background and overwrites it with the optimised version. See [[Jobs + Queues (ProcessPostImage)]].

### Error handling

```php
try {
    Image::read($path)->scaleDown(width: 1200)->toJpeg(quality: 80)->save($path);
} catch (\Throwable $e) {
    Log::warning('Failed to process post image', [
        'path'  => $this->imagePath,
        'error' => $e->getMessage(),
    ]);
}
```

If processing fails (corrupt file, unsupported format, out of memory), the original file is preserved and the failure is logged as a warning — the post remains functional with the unoptimised image.

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

Constraining uploads to 1200px wide and JPEG format means consistent, predictable file sizes on disk. Without this, users could upload 20MB PNG files that get served to every visitor of the post's page. The in-place overwrite (same path) means no database update is required after processing — the stored path is still correct.

---

## Related Notes

- [[Jobs + Queues (ProcessPostImage)]]
- [[Posts (replies + reposts)]]
- [[Avatar Upload]]
