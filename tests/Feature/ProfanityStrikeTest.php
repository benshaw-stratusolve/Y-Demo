<?php

use App\Models\Post;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['profanity_strikes' => 0]);
    $this->actingAs($this->user);
});

test('posting profane content issues a strike', function () {
    $this->post('/posts', ['body' => 'what the fuck'])
        ->assertSessionHasErrors('profanity_strike')
        ->assertSessionDoesntHaveErrors('body');

    expect($this->user->fresh()->profanity_strikes)->toBe(1);
    expect($this->user->fresh()->banned_at)->toBeNull();
});

test('profane reply issues a strike under profanity_strike key', function () {
    $post = Post::factory()->create();

    $this->post("/posts/{$post->id}/reply", ['body' => 'what the fuck'])
        ->assertSessionHasErrors('profanity_strike')
        ->assertSessionDoesntHaveErrors('body');

    expect($this->user->fresh()->profanity_strikes)->toBe(1);
});

test('third profane post bans the user', function () {
    $this->user->forceFill(['profanity_strikes' => 2])->save();

    $this->post('/posts', ['body' => 'absolute bullshit'])
        ->assertSessionHasErrors('account_banned');

    expect($this->user->fresh()->banned_at)->not->toBeNull();
});

test('banned user cannot accumulate more strikes via profane post', function () {
    $this->user->forceFill(['banned_at' => now(), 'profanity_strikes' => 3])->save();

    $this->post('/posts', ['body' => 'what the fuck'])
        ->assertSessionHasErrors('account_banned');

    expect($this->user->fresh()->profanity_strikes)->toBe(3);
});
