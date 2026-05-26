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

test('banned user cannot create a post', function () {
    $this->user->forceFill(['banned_at' => now()])->save();

    $this->post('/posts', ['body' => 'Hello world'])
        ->assertSessionHasErrors('account_banned');

    expect($this->user->posts()->count())->toBe(0);
});

test('banned user cannot reply', function () {
    $this->user->forceFill(['banned_at' => now()])->save();
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/reply", ['body' => 'Hello'])
        ->assertSessionHasErrors('account_banned');

    expect(Post::where('parent_post_id', $post->id)->count())->toBe(0);
});

test('banned user cannot like a post', function () {
    $this->user->forceFill(['banned_at' => now()])->save();
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/like")
        ->assertSessionHasErrors('account_banned');

    expect(Like::where('user_id', $this->user->id)->where('post_id', $post->id)->exists())->toBeFalse();
});

test('banned user cannot repost', function () {
    $this->user->forceFill(['banned_at' => now()])->save();
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/repost")
        ->assertSessionHasErrors('account_banned');

    expect($this->user->posts()->where('repost_of_id', $post->id)->exists())->toBeFalse();
});

test('post body cannot exceed 280 characters', function () {
    $this->post('/posts', ['body' => str_repeat('a', 281)])
        ->assertSessionHasErrors('body');
});

test('reply body cannot exceed 280 characters', function () {
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/reply", ['body' => str_repeat('a', 281)])
        ->assertSessionHasErrors('body');
});

test('reply body is required', function () {
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/reply", ['body' => ''])
        ->assertSessionHasErrors('body');
});

test('user cannot post more than 5 comments within 5 minutes', function () {
    $post = Post::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post("/posts/{$post->id}/reply", ['body' => "Comment {$i}"])
            ->assertSessionHasNoErrors();
    }

    $this->post("/posts/{$post->id}/reply", ['body' => 'One too many'])
        ->assertSessionHasErrors('reply_limit');
});

test('fifth comment is still allowed', function () {
    $post = Post::factory()->create();

    foreach (range(1, 4) as $i) {
        $this->post("/posts/{$post->id}/reply", ['body' => "Comment {$i}"]);
    }

    $this->post("/posts/{$post->id}/reply", ['body' => 'Comment 5'])
        ->assertSessionHasNoErrors();
});

test('show page returns other posts by the same author', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'Main post']);
    $other = Post::factory()->create(['user_id' => $author->id, 'body' => 'Another post']);

    $this->get("/posts/{$post->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('authorPosts', 1)
            ->where('authorPosts.0.id', $other->id)
        );
});

test('show page excludes the current post from author posts', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'Main post']);

    $this->get("/posts/{$post->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('authorPosts', 0));
});

test('show page excludes reposts from author posts', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'Main post']);
    $original = Post::factory()->create();
    Post::factory()->create(['user_id' => $author->id, 'repost_of_id' => $original->id, 'body' => null]);

    $this->get("/posts/{$post->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('authorPosts', 0));
});

test('show page excludes replies from author posts', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'Main post']);
    $parent = Post::factory()->create();
    Post::factory()->create(['user_id' => $author->id, 'parent_post_id' => $parent->id, 'body' => 'A reply']);

    $this->get("/posts/{$post->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('authorPosts', 0));
});

test('show page includes like and reply counts in author posts', function () {
    $author = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'Main post']);
    $other = Post::factory()->create(['user_id' => $author->id, 'body' => 'Another post']);

    $this->get("/posts/{$post->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('authorPosts.0.likes_count')
            ->has('authorPosts.0.replies_count')
        );
});
