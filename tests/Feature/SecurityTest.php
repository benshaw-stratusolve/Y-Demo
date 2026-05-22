<?php

use App\Models\Post;
use App\Models\User;

test('user cannot follow themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('users.follow', $user))
        ->assertStatus(403);
});

test('user cannot repost a repost', function () {
    $author = User::factory()->create();
    $reposter = User::factory()->create();
    $user = User::factory()->create();

    $original = Post::factory()->for($author)->create(['body' => 'original']);
    $repost = Post::factory()->for($reposter)->create(['repost_of_id' => $original->id]);

    $this->actingAs($user)
        ->post(route('posts.repost', $repost))
        ->assertStatus(422);
});

test('user can repost an original post', function () {
    $author = User::factory()->create();
    $user = User::factory()->create();

    $original = Post::factory()->for($author)->create(['body' => 'original']);

    $this->actingAs($user)
        ->post(route('posts.repost', $original))
        ->assertRedirectBack();

    expect($user->posts()->where('repost_of_id', $original->id)->exists())->toBeTrue();
});
