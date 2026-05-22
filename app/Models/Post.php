<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'body', 'parent_post_id', 'repost_of_id', 'image'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $appends = ['image_url'];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->image ? Storage::disk('public')->url($this->image) : null
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Deleted User',
            'username' => 'deleted',
        ]);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'parent_post_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Post::class, 'parent_post_id');
    }

    public function repostOf(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'repost_of_id');
    }

    public function reposts(): HasMany
    {
        return $this->hasMany(Post::class, 'repost_of_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }
}
