<?php

use App\Models\User;

test('unauthenticated user is redirected to admin login', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

test('non-admin user cannot access the admin panel', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('admin user can access the admin panel', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});
