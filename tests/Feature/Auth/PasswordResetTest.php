<?php

use App\Models\User;
use App\Notifications\PasswordResetCompletedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->get(route('password.reset', $notification->token).'?email='.$user->email);

        $response->assertOk()->assertInertia(
            fn ($page) => $page->where('tokenInvalid', false)
        );

        return true;
    });
});

test('reset password page shows used reason when token record is gone', function () {
    $user = User::factory()->create();
    // No record in password_reset_tokens = link was already used

    $response = $this->get(route('password.reset', 'any-token').'?email='.$user->email);

    $response->assertOk()->assertInertia(
        fn ($page) => $page->where('tokenInvalid', true)->where('tokenInvalidReason', 'used')
    );
});

test('clicking reset link after already resetting password shows already-used message', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    // Use the token to reset password
    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'P@ssword1',
        'password_confirmation' => 'P@ssword1',
    ])->assertSessionHasNoErrors();

    // Click the link again with the same (now-deleted) token
    $this->get(route('password.reset', $token).'?email='.$user->email)
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('tokenInvalid', true)
                ->where('tokenInvalidReason', 'used')
        );
});

test('reset password page shows expired reason when token record exists but is old', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(16)->toDateTimeString()]);

    $response = $this->get(route('password.reset', $token).'?email='.$user->email);

    $response->assertOk()->assertInertia(
        fn ($page) => $page->where('tokenInvalid', true)->where('tokenInvalidReason', 'expired')
    );
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'P@ssword1',
            'password_confirmation' => 'P@ssword1',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('successful password reset is stored in notifications', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'P@ssword1',
        'password_confirmation' => 'P@ssword1',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $notification = $user->notifications()
        ->where('type', PasswordResetCompletedNotification::class)
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data)->toMatchArray([
            'type' => 'password_reset',
            'message' => 'Your password was reset successfully.',
        ]);
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

test('password reset link expires after 15 minutes', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    // Back-date the stored token so it looks 16 minutes old
    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(16)->toDateTimeString()]);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'P@ssword1',
        'password_confirmation' => 'P@ssword1',
    ]);

    $response->assertSessionHasErrors('email');

    // Confirm the user's password did NOT change
    $user->refresh();
    expect(Hash::check('password', $user->password))->toBeTrue();
});

test('password reset token cannot be reused after successful reset', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    // First use — succeeds and invalidates the token
    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'P@ssword1',
        'password_confirmation' => 'P@ssword1',
    ])->assertSessionHasNoErrors();

    // Second use with the same token — must fail
    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'An0therP@ss!',
        'password_confirmation' => 'An0therP@ss!',
    ]);

    $response->assertSessionHasErrors('email');

    // Confirm the password is still the first reset value, not the second
    $user->refresh();
    expect(Hash::check('P@ssword1', $user->password))->toBeTrue();
});
