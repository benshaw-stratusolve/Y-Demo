<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['user1_id', 'user2_id', 'deleted_by_user1', 'deleted_by_user2', 'user1_cleared_at', 'user2_cleared_at'];

    protected $casts = [
        'deleted_by_user1' => 'boolean',
        'deleted_by_user2' => 'boolean',
        'user1_cleared_at' => 'datetime',
        'user2_cleared_at' => 'datetime',
    ];

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function otherUser(int $userId): User
    {
        return $this->user1_id === $userId ? $this->user2 : $this->user1;
    }

    public function unreadCount(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function clearFor(int $userId): void
    {
        if ($this->user1_id === $userId) {
            $this->update(['user1_cleared_at' => now()]);
        } else {
            $this->update(['user2_cleared_at' => now()]);
        }
    }

    public function clearedAtFor(int $userId): mixed
    {
        return $this->user1_id === $userId ? $this->user1_cleared_at : $this->user2_cleared_at;
    }

    public static function findOrCreateBetween(int $userIdA, int $userIdB): self
    {
        return static::firstOrCreate([
            'user1_id' => min($userIdA, $userIdB),
            'user2_id' => max($userIdA, $userIdB),
        ]);
    }
}
