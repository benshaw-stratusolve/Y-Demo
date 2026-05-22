<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProcessPostImage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $imagePath)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! Storage::disk('public')->exists($this->imagePath)) {
            return;
        }

        $path = Storage::disk('public')->path($this->imagePath);

        try {
            Image::read($path)
                ->scaleDown(width: 1200)
                ->toJpeg(quality: 80)
                ->save($path);
        } catch (\Throwable $e) {
            Log::warning('Failed to process post image', [
                'path' => $this->imagePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
