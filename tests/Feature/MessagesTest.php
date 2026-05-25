<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

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
