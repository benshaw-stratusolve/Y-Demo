<?php

use App\Mail\WelcomeEmail;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

it('sends a welcome email when a user registers', function () {
    Mail::fake();

    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    Mail::assertSent(WelcomeEmail::class, fn ($mail) => $mail->hasTo('test@example.com'));
});

it('sends a welcome in-app notification when a user registers', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Test User',
        'username' => 'testuser2',
        'email' => 'test2@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);

    $user = \App\Models\User::where('email', 'test2@example.com')->first();
    Notification::assertSentTo($user, WelcomeNotification::class);
});
