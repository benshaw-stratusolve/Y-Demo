<?php

use App\Models\Post;
use App\Models\User;
use App\Notifications\FollowNotification;
use App\Notifications\LikeNotification;
use App\Notifications\PostDeletedNotification;
use App\Notifications\ProfanityStrikeNotification;
use App\Notifications\ReplyNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('LikeNotification implements ShouldBroadcast', function () {
    expect(LikeNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('FollowNotification implements ShouldBroadcast', function () {
    expect(FollowNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('ReplyNotification implements ShouldBroadcast', function () {
    expect(ReplyNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('PostDeletedNotification implements ShouldBroadcast', function () {
    expect(PostDeletedNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('ProfanityStrikeNotification implements ShouldBroadcast', function () {
    expect(ProfanityStrikeNotification::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

test('LikeNotification is sent when a post is liked', function () {
    Notification::fake();

    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $this->post("/posts/{$post->id}/like");

    Notification::assertSentTo($author, LikeNotification::class);
});

test('FollowNotification is sent when a user is followed', function () {
    Notification::fake();

    $target = User::factory()->create();

    $this->post("/users/{$target->id}/follow");

    Notification::assertSentTo($target, FollowNotification::class);
});
