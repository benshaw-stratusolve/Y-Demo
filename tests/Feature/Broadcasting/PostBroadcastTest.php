<?php

use App\Events\PostBroadcast;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('PostBroadcast is dispatched to each follower when a post is created', function () {
    Event::fake([PostBroadcast::class]);

    $follower = User::factory()->create();
    $follower->followedUsers()->attach($this->user->id);

    $this->post('/posts', ['body' => 'Hello world']);

    Event::assertDispatched(PostBroadcast::class, function (PostBroadcast $event) use ($follower) {
        return $event->follower->id === $follower->id;
    });
});

test('PostBroadcast is not dispatched when the author has no followers', function () {
    Event::fake([PostBroadcast::class]);

    $this->post('/posts', ['body' => 'Hello world']);

    Event::assertNotDispatched(PostBroadcast::class);
});
