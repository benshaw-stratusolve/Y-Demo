<?php

use App\Models\User;
use Illuminate\Database\QueryException;

it('stores user profile fields', function () {
    $user = User::factory()->create([
        'username' => 'benshaw',
        'bio' => 'Building cool stuff',
        'location' => 'London',
        'website' => 'https://benshaw.dev',
        'avatar' => null,
    ]);

    expect($user->refresh())
        ->username->toBe('benshaw')
        ->bio->toBe('Building cool stuff')
        ->location->toBe('London')
        ->website->toBe('https://benshaw.dev')
        ->avatar->toBeNull();
});

it('enforces unique usernames', function () {
    User::factory()->create(['username' => 'duplicate']);

    expect(fn () => User::factory()->create(['username' => 'duplicate']))
        ->toThrow(QueryException::class);
});

use App\Models\Post;

it('creates a top-level post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create(['body' => 'Hello world']);

    expect($post->refresh())
        ->user_id->toBe($user->id)
        ->body->toBe('Hello world')
        ->parent_post_id->toBeNull()
        ->repost_of_id->toBeNull();
});

it('creates a reply by setting parent_post_id', function () {
    $parent = Post::factory()->create();
    $reply = Post::factory()->create(['parent_post_id' => $parent->id]);

    expect($reply->parent->id)->toBe($parent->id);
    expect($parent->replies)->toHaveCount(1);
});

it('creates a retweet by setting repost_of_id', function () {
    $original = Post::factory()->create();
    $retweet = Post::factory()->create(['repost_of_id' => $original->id, 'body' => null]);

    expect($retweet->repostOf->id)->toBe($original->id);
    expect($original->reposts)->toHaveCount(1);
});

it('user has many posts', function () {
    $user = User::factory()->create();
    Post::factory()->count(3)->for($user)->create();

    expect($user->posts)->toHaveCount(3);
});

use App\Models\Like;

it('user can like a post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    Like::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);

    expect($post->likes)->toHaveCount(1);
    expect($user->likes)->toHaveCount(1);
});

it('enforces one like per user per post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create();
    Like::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]);

    expect(fn () => Like::factory()->create(['user_id' => $user->id, 'post_id' => $post->id]))
        ->toThrow(QueryException::class);
});

use App\Models\Follow;

it('user can follow another user', function () {
    $follower = User::factory()->create();
    $following = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'following_id' => $following->id]);

    expect($follower->follows)->toHaveCount(1);
});

it('enforces unique follows', function () {
    $follower = User::factory()->create();
    $following = User::factory()->create();
    Follow::factory()->create(['follower_id' => $follower->id, 'following_id' => $following->id]);

    expect(fn () => Follow::factory()->create(['follower_id' => $follower->id, 'following_id' => $following->id]))->toThrow(QueryException::class);
});

use App\Models\Media;

it('attaches media to a post', function () {
    $post = Post::factory()->create();
    Media::create(['post_id' => $post->id, 'path' => 'images/photo.jpg', 'type' => 'image', 'sort_order' => 0]);

    expect($post->media)->toHaveCount(1);
    expect($post->media->first()->type)->toBe('image');
});

use App\Models\Conversation;
use App\Models\Message;

it('creates a conversation with participants', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $conversation->participants()->attach([$userA->id, $userB->id]);

    expect($conversation->participants)->toHaveCount(2);
    expect($userA->conversations)->toHaveCount(1);
});

it('sends a message in a conversation', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $conversation->participants()->attach($user->id);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'body' => 'Hey there!',
    ]);

    expect($conversation->messages)->toHaveCount(1);
    expect($conversation->messages->first()->body)->toBe('Hey there!');
    expect($message->read_at)->toBeNull();
});
