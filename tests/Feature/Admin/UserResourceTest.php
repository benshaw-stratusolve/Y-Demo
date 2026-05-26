<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('admin can see all users', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

test('admin can ban a user', function () {
    $user = User::factory()->create(['banned_at' => null]);

    Livewire::test(ListUsers::class)
        ->callTableAction('ban', $user)
        ->assertHasNoTableActionErrors();

    expect($user->fresh()->banned_at)->not->toBeNull();
});

test('admin can unban a user', function () {
    $user = User::factory()->create(['banned_at' => now(), 'profanity_strikes' => 3]);

    Livewire::test(ListUsers::class)
        ->callTableAction('unban', $user)
        ->assertHasNoTableActionErrors();

    expect($user->fresh()->banned_at)->toBeNull();
    expect($user->fresh()->profanity_strikes)->toBe(0);
});

test('admin cannot demote themselves — action is hidden', function () {
    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('toggle_admin', $this->admin);
});

test('admin can reset profanity strikes', function () {
    $user = User::factory()->create(['profanity_strikes' => 2]);

    Livewire::test(ListUsers::class)
        ->callTableAction('reset_strikes', $user)
        ->assertHasNoTableActionErrors();

    expect($user->fresh()->profanity_strikes)->toBe(0);
});

test('admin can promote a user to admin', function () {
    $user = User::factory()->create(['is_admin' => false]);

    Livewire::test(ListUsers::class)
        ->callTableAction('toggle_admin', $user)
        ->assertHasNoTableActionErrors();

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('admin cannot create a user with an existing email', function () {
    $existing = User::factory()->create(['email' => 'taken@example.com']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New User',
            'username' => 'brandnewuser',
            'email' => 'taken@example.com',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

test('admin cannot create a user with an existing username', function () {
    $existing = User::factory()->create(['username' => 'takenhandle']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New User',
            'username' => 'takenhandle',
            'email' => 'unique@example.com',
            'password' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['username' => 'unique']);
});
