<?php

use App\Models\User;
use Illuminate\Support\Str;

it('clears all notifications for the authenticated user only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test-notification',
        'data' => ['type' => 'test', 'message' => 'First notification'],
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test-notification',
        'data' => ['type' => 'test', 'message' => 'Second notification'],
    ]);
    $otherUser->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test-notification',
        'data' => ['type' => 'test', 'message' => 'Other notification'],
    ]);

    $this->actingAs($user)
        ->delete('/notifications')
        ->assertRedirect();

    expect($user->notifications()->count())->toBe(0)
        ->and($otherUser->notifications()->count())->toBe(1);
});
