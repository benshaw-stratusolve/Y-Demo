<?php

use App\Models\Like;
use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('user can create a post', function () {
    $this->post('/posts', ['body' => 'Hello world'])
        ->assertRedirect('/dashboard');

    expect($this->user->posts()->where('body', 'Hello world')->exists())->toBeTrue();
});

test('post body is required', function () {
    $this->post('/posts', ['body' => ''])
        ->assertSessionHasErrors('body');
});

test('user can like a post', function () {
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/like");

    expect(Like::where('user_id', $this->user->id)->where('post_id', $post->id)->exists())->toBeTrue();
});

test('user can unlike a post', function () {
    $post = Post::factory()->create();
    Like::factory()->create(['user_id' => $this->user->id, 'post_id' => $post->id]);

    $this->post("/posts/{$post->id}/like");

    expect(Like::where('user_id', $this->user->id)->where('post_id', $post->id)->exists())->toBeFalse();
});

test('user can repost', function () {
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/repost");

    expect($this->user->posts()->where('repost_of_id', $post->id)->exists())->toBeTrue();
});

test('user can undo a repost', function () {
    $post = Post::factory()->create();
    Post::factory()->create(['user_id' => $this->user->id, 'repost_of_id' => $post->id]);

    $this->post("/posts/{$post->id}/repost");

    expect($this->user->posts()->where('repost_of_id', $post->id)->exists())->toBeFalse();
});

test('user can reply to a post', function () {
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/reply", ['body' => 'Great post!']);

    expect(Post::where('parent_post_id', $post->id)->where('body', 'Great post!')->exists())->toBeTrue();
});

test('user can delete their own post', function () {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    $this->delete("/posts/{$post->id}")->assertRedirect();

    expect(Post::find($post->id))->toBeNull();
});

test('user can delete their own comment', function () {
    $post = Post::factory()->create();
    $comment = Post::factory()->create([
        'user_id' => $this->user->id,
        'parent_post_id' => $post->id,
        'body' => 'My comment',
    ]);

    $this->delete("/posts/{$comment->id}")->assertRedirect();

    expect(Post::find($comment->id))->toBeNull();
});

test('user cannot delete another users post', function () {
    $post = Post::factory()->create();

    $this->delete("/posts/{$post->id}")->assertForbidden();

    expect(Post::find($post->id))->not->toBeNull();
});

test('user cannot delete another users comment', function () {
    $post = Post::factory()->create();
    $comment = Post::factory()->create([
        'parent_post_id' => $post->id,
        'body' => 'Someone elses comment',
    ]);

    $this->delete("/posts/{$comment->id}")->assertForbidden();

    expect(Post::find($comment->id))->not->toBeNull();
});
