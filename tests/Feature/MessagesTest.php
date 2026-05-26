<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('can send an image with a message in a conversation', function () {
    Storage::fake('public');
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}", [
            'body' => 'Look at this',
            'image' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ])
        ->assertRedirect();

    $message = $conv->messages()->first();

    expect($message->body)->toBe('Look at this')
        ->and($message->image_path)->toStartWith('message-images/');

    Storage::disk('public')->assertExists($message->image_path);
});

it('can send an image without text in a conversation', function () {
    Storage::fake('public');
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}", [
            'image' => UploadedFile::fake()->image('photo.png', 640, 480),
        ])
        ->assertRedirect();

    $message = $conv->messages()->first();

    expect($message->body)->toBe('')
        ->and($message->image_path)->toStartWith('message-images/');

    Storage::disk('public')->assertExists($message->image_path);
});

it('includes image urls when viewing a conversation', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $message = $conv->messages()->create([
        'sender_id' => $userA->id,
        'body' => '',
        'image_path' => 'message-images/photo.jpg',
    ]);
    $imageUrl = Storage::disk('public')->url('message-images/photo.jpg');

    $this->actingAs($userB)
        ->get("/messages/{$conv->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('messages.0.id', $message->id)
            ->where('messages.0.image_url', $imageUrl)
        );
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

it('message image must be an image file', function () {
    Storage::fake('public');
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}", [
            'body' => 'Not an image',
            'image' => UploadedFile::fake()->create('document.pdf', 64, 'application/pdf'),
        ])
        ->assertInvalid(['image']);
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

it('hides a conversation only for the deleting user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv->messages()->create(['sender_id' => $userA->id, 'body' => 'Hi', 'read_at' => null]);

    $this->actingAs($userA)
        ->delete("/messages/{$conv->id}")
        ->assertRedirect('/messages');

    $conv->refresh();
    $isUser1 = $conv->user1_id === $userA->id;
    expect($isUser1 ? $conv->deleted_by_user1 : $conv->deleted_by_user2)->toBeTrue();
    expect($isUser1 ? $conv->deleted_by_user2 : $conv->deleted_by_user1)->toBeFalse();
});

it('restores conversation visibility for both when a new message is sent', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv->update(['deleted_by_user1' => true, 'deleted_by_user2' => true]);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}", ['body' => 'Back again!'])
        ->assertRedirect();

    $conv->refresh();
    expect($conv->deleted_by_user1)->toBeFalse();
    expect($conv->deleted_by_user2)->toBeFalse();
});

it('cannot delete a conversation they do not belong to', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userC)
        ->delete("/messages/{$conv->id}")
        ->assertStatus(403);
});

it('clear chat sets the cleared_at timestamp for the requesting user only', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userA)
        ->post("/messages/{$conv->id}/clear")
        ->assertRedirect("/messages/{$conv->id}");

    $conv->refresh();
    $isUser1 = $conv->user1_id === $userA->id;
    expect($isUser1 ? $conv->user1_cleared_at : $conv->user2_cleared_at)->not->toBeNull();
    expect($isUser1 ? $conv->user2_cleared_at : $conv->user1_cleared_at)->toBeNull();
});

it('messages sent before clear are hidden for the clearing user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv->messages()->create(['sender_id' => $userB->id, 'body' => 'Old message']);

    // Set clear timestamp to now so old message is before it
    $conv->clearFor($userA->id);

    $visible = $conv->messages()
        ->when($conv->clearedAtFor($userA->id), fn ($q) => $q->where('created_at', '>', $conv->clearedAtFor($userA->id)))
        ->get();

    expect($visible)->toHaveCount(0);

    // userB's view is unaffected
    $visibleForB = $conv->messages()
        ->when($conv->clearedAtFor($userB->id), fn ($q) => $q->where('created_at', '>', $conv->clearedAtFor($userB->id)))
        ->get();

    expect($visibleForB)->toHaveCount(1);
});

it('cleared history stays hidden even after sending a new message', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);
    $conv->messages()->create(['sender_id' => $userB->id, 'body' => 'Old message']);

    $conv->clearFor($userA->id);

    // Send a new message after clearing
    $this->actingAs($userA)->post("/messages/{$conv->id}", ['body' => 'New message']);

    $conv->refresh();
    // Clear timestamp must not be reset by sending a message
    $isUser1 = $conv->user1_id === $userA->id;
    expect($isUser1 ? $conv->user1_cleared_at : $conv->user2_cleared_at)->not->toBeNull();
});

it('cannot clear a conversation they do not belong to', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userC)
        ->post("/messages/{$conv->id}/clear")
        ->assertStatus(403);
});

it('user can mute another user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)
        ->post("/users/{$userB->id}/mute")
        ->assertRedirect();

    expect($userA->hasMuted($userB->id))->toBeTrue();
});

it('user can unmute a previously muted user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userA->mutedUsers()->attach($userB->id);

    $this->actingAs($userA)
        ->post("/users/{$userB->id}/mute")
        ->assertRedirect();

    expect($userA->hasMuted($userB->id))->toBeFalse();
});

it('cannot mute yourself', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/users/{$user->id}/mute")
        ->assertStatus(422);
});

it('muted user can still send messages but the recipient receives no real-time notification', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userA->mutedUsers()->attach($userB->id);
    $conv = Conversation::findOrCreateBetween($userA->id, $userB->id);

    $this->actingAs($userB)
        ->post("/messages/{$conv->id}", ['body' => 'Hello!'])
        ->assertRedirect();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conv->id,
        'sender_id' => $userB->id,
        'body' => 'Hello!',
    ]);
});
