<?php

use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('admin can see all posts', function () {
    $posts = Post::factory()->count(3)->create();

    Livewire::test(ListPosts::class)
        ->assertCanSeeTableRecords($posts);
});

test('admin can delete a post', function () {
    $post = Post::factory()->create();

    Livewire::test(ListPosts::class)
        ->callTableAction('delete', $post)
        ->assertHasNoTableActionErrors();

    expect(Post::find($post->id))->toBeNull();
});

test('non-admin cannot access admin posts', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/admin/posts')
        ->assertForbidden();
});
