<?php

use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('short search query returns an empty result set', function () {
    $this->getJson('/search?q=a')
        ->assertSuccessful()
        ->assertJson([
            'query' => 'a',
            'type' => 'profiles',
            'users' => [],
            'posts' => [],
            'suggestions' => [],
        ]);
});

test('profile search returns matching users and suggestions', function () {
    $alice = User::factory()->create([
        'name' => 'Alice Search',
        'username' => 'alice',
    ]);

    User::factory()->create([
        'name' => 'Bob Builder',
        'username' => 'builder',
    ]);

    Post::factory()->create([
        'user_id' => $alice->id,
        'body' => 'Responsive search should surface useful suggestions.',
    ]);

    $response = $this->getJson('/search?q=search&type=profiles');

    $response
        ->assertSuccessful()
        ->assertJsonPath('query', 'search')
        ->assertJsonPath('type', 'profiles')
        ->assertJsonPath('users.0.id', $alice->id)
        ->assertJsonPath('posts', []);

    expect($response->json('suggestions'))->toContain('Alice Search');
});

test('post search returns matching posts without profiles', function () {
    $alice = User::factory()->create([
        'name' => 'Alice Search',
        'username' => 'alice',
    ]);

    $post = Post::factory()->create([
        'user_id' => $alice->id,
        'body' => 'Responsive search should surface useful suggestions.',
    ]);

    $response = $this->getJson('/search?q=search&type=posts');

    $response
        ->assertSuccessful()
        ->assertJsonPath('query', 'search')
        ->assertJsonPath('type', 'posts')
        ->assertJsonPath('users', [])
        ->assertJsonPath('posts.0.id', $post->id);
});

test('search suggests similar terms when there are no exact matches', function () {
    User::factory()->create([
        'name' => 'Alice Search',
        'username' => 'alice',
    ]);

    $response = $this->getJson('/search?q=alce&type=profiles');

    $response
        ->assertSuccessful()
        ->assertJsonPath('users', [])
        ->assertJsonPath('posts', []);

    expect($response->json('suggestions'))->toContain('Alice Search');
});
