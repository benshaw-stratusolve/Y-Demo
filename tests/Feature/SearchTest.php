<?php

use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('short search query returns recent posts and no users', function () {
    $this->getJson('/search?q=a')
        ->assertSuccessful()
        ->assertJson([
            'query' => 'a',
            'users' => [],
            'suggestions' => [],
        ]);
});

test('search returns matching users and posts together', function () {
    $alice = User::factory()->create([
        'name' => 'Alice Search',
        'username' => 'alice',
    ]);

    $post = Post::factory()->create([
        'user_id' => $alice->id,
        'body' => 'Responsive search should surface useful suggestions.',
    ]);

    $response = $this->getJson('/search?q=search');

    $response
        ->assertSuccessful()
        ->assertJsonPath('query', 'search')
        ->assertJsonPath('users.0.id', $alice->id)
        ->assertJsonPath('posts.0.id', $post->id);

    expect($response->json('suggestions'))->toContain('Alice Search');
});

test('search returns matching posts', function () {
    $alice = User::factory()->create([
        'name' => 'Alice Wonder',
        'username' => 'alice',
    ]);

    $post = Post::factory()->create([
        'user_id' => $alice->id,
        'body' => 'Responsive search should surface useful suggestions.',
    ]);

    $response = $this->getJson('/search?q=Responsive');

    $response
        ->assertSuccessful()
        ->assertJsonPath('posts.0.id', $post->id);
});

test('search suggests similar terms when there are no exact matches', function () {
    User::factory()->create([
        'name' => 'Alice Search',
        'username' => 'alice',
    ]);

    $response = $this->getJson('/search?q=alce');

    $response
        ->assertSuccessful()
        ->assertJsonPath('users', [])
        ->assertJsonPath('posts', []);

    expect($response->json('suggestions'))->toContain('Alice Search');
});
