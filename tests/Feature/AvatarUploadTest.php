<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('user can upload an avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('avatar.jpg');

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), ['avatar' => $file])
        ->assertRedirect(route('profile.edit'));

    Storage::disk('public')->assertExists('avatars/'.$file->hashName());
    expect($user->fresh()->avatar)->not->toBeNull();
});

test('avatar upload requires an image', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), ['avatar' => 'not-a-file'])
        ->assertSessionHasErrors('avatar');
});

test('user can remove their avatar', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('avatar.jpg');
    $path = $file->store('avatars', 'public');

    $user = User::factory()->create(['avatar' => $path]);

    $this->actingAs($user)
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('profile.edit'));

    Storage::disk('public')->assertMissing($path);
    expect($user->fresh()->avatar)->toBeNull();
});

test('avatar_url accessor returns full url when avatar is set', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('avatar.jpg');
    $path = $file->store('avatars', 'public');

    $user = User::factory()->create(['avatar' => $path]);

    expect($user->avatar_url)->toContain('avatars/');
});

test('avatar_url accessor returns null when no avatar', function () {
    $user = User::factory()->create(['avatar' => null]);

    expect($user->avatar_url)->toBeNull();
});
