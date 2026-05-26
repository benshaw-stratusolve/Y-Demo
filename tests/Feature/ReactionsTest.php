<?php

use App\Models\Conversation;
use App\Models\User;

it('user can add a reaction to a message', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $message = $conv->messages()->create(['sender_id' => $userB->id, 'body' => 'Hello!']);

    $this->actingAs($userA)
        ->post("/messages/{$message->id}/react", ['emoji' => '👍'])
        ->assertRedirect();

    $this->assertDatabaseHas('reactions', [
        'message_id' => $message->id,
        'user_id' => $userA->id,
        'emoji' => '👍',
    ]);
});

it('user can remove a reaction by toggling the same emoji', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $message = $conv->messages()->create(['sender_id' => $userB->id, 'body' => 'Hello!']);
    $message->reactions()->create(['user_id' => $userA->id, 'emoji' => '👍']);

    $this->actingAs($userA)
        ->post("/messages/{$message->id}/react", ['emoji' => '👍'])
        ->assertRedirect();

    $this->assertDatabaseMissing('reactions', [
        'message_id' => $message->id,
        'user_id' => $userA->id,
        'emoji' => '👍',
    ]);
});

it('multiple users can react with the same emoji', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $message = $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hello!']);

    $this->actingAs($userA)->post("/messages/{$message->id}/react", ['emoji' => '❤️']);
    $this->actingAs($userB)->post("/messages/{$message->id}/react", ['emoji' => '❤️']);

    expect($message->reactions()->where('emoji', '❤️')->count())->toBe(2);
});

it('user cannot react to a message in a conversation they do not belong to', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $message = $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hello!']);

    $this->actingAs($userC)
        ->post("/messages/{$message->id}/react", ['emoji' => '👍'])
        ->assertStatus(403);
});

it('emoji is required when reacting', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $message = $conv->messages()->create(['sender_id' => $userB->id, 'body' => 'Hello!']);

    $this->actingAs($userA)
        ->post("/messages/{$message->id}/react", [])
        ->assertInvalid(['emoji']);
});
