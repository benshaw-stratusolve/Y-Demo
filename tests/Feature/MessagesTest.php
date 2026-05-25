<?php

use App\Models\Conversation;
use App\Models\User;
use App\Models\Message;

it('creates a canonical conversation between two users', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    expect($conv->user1_id)->toBe(min($userA->id, $userB->id));
    expect($conv->user2_id)->toBe(max($userA->id, $userB->id));
});

it('finds existing conversation instead of creating a duplicate', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Conversation::findOrCreateBetween($userA->id, $userB->id);
    Conversation::findOrCreateBetween($userA->id, $userB->id);

    expect(Conversation::count())->toBe(1);
});

it('returns the other user in a conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    expect($conv->otherUser($userA->id)->id)->toBe($userB->id);
    expect($conv->otherUser($userB->id)->id)->toBe($userA->id);
});

it('counts unread messages correctly', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hi']);
    $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hey']);

    expect($conv->unreadCount($userB->id))->toBe(2);
    expect($conv->unreadCount($userA->id))->toBe(0);
});

it('counts total unread messages for a user across all conversations', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();

    $conv1 = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv2 = Conversation::findOrCreateBetween($userA->id, $userC->id);

    $conv1->messages()->create(['sender_id' => $userB->id, 'body' => 'Hi from B']);
    $conv2->messages()->create(['sender_id' => $userC->id, 'body' => 'Hi from C']);

    expect($userA->unreadMessagesCount())->toBe(2);
    expect($userB->unreadMessagesCount())->toBe(0);
});

it('redirects to messages page when starting a conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $response = $this->actingAs($userA)
        ->post("/conversations/with/{$userB->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('conversations', [
        'user1_id' => min($userA->id, $userB->id),
        'user2_id' => max($userA->id, $userB->id),
    ]);
});

it('cannot start a conversation with yourself', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/conversations/with/{$user->id}")
        ->assertStatus(422);
});

it('can send a message in a conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}", ['body' => 'Hello!'])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conv->id,
        'sender_id' => $userA->id,
        'body' => 'Hello!',
    ]);
});

it('cannot send a message in a conversation they do not belong to', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userC)
        ->post("/messages/{$conv->id}", ['body' => 'Hack!'])
        ->assertStatus(403);
});

it('message body is required', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}", ['body' => ''])
        ->assertInvalid(['body']);
});

it('marks messages as read when conversation is viewed', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hi', 'read_at' => null]);

    $this->actingAs($userB)->get("/messages/{$conv->id}");

    expect($conv->messages()->whereNull('read_at')->count())->toBe(0);
});

it('cannot view a conversation they do not belong to', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userC)
        ->get("/messages/{$conv->id}")
        ->assertStatus(403);
});
