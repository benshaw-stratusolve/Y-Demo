<?php

use App\Models\User;
use App\Notifications\PasswordUpdateNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('security page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->has('passwordRules'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'P@ssword1',
            'password_confirmation' => 'P@ssword1',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('P@ssword1', $user->refresh()->password))->toBeTrue();
});

test('password cannot be updated to the same password', function () {
    $user = User::factory()->create(['password' => Hash::make('P@ssword1')]);

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'P@ssword1',
            'password' => 'P@ssword1',
            'password_confirmation' => 'P@ssword1',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('security.edit'));
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'P@ssword1',
            'password_confirmation' => 'P@ssword1',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});

test('password update notification is sent via mail and database channels', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'P@ssword1',
            'password_confirmation' => 'P@ssword1',
        ]);

    Notification::assertSentTo($user, PasswordUpdateNotification::class, function ($notification) use ($user) {
        return in_array('mail', $notification->via($user))
            && in_array('database', $notification->via($user));
    });
});

test('password update notification toArray contains correct type', function () {
    $user = User::factory()->create();
    $data = (new PasswordUpdateNotification)->toArray($user);

    expect($data['type'])->toBe('password_updated')
        ->and($data['message'])->toContain('contact support');
});

test('password update notification uses the branded markdown mail layout', function () {
    $user = User::factory()->create([
        'name' => 'Taylor Otwell',
    ]);

    $message = (new PasswordUpdateNotification)->toMail($user);
    $html = (string) $message->render();

    expect($message->markdown)->toBe('mail.password-updated')
        ->and($html)->toContain('Your password was updated, Taylor Otwell')
        ->and($html)->toContain('Go to Y')
        ->and($html)->toContain('The Y Team');
});
