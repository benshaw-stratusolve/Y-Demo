<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('username must be at least 3 characters at registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'ab',
        'email' => 'x@example.com',
        'password' => 'P@ssword1',
        'password_confirmation' => 'P@ssword1',
    ])->assertSessionHasErrors('username');
});

test('username cannot contain special characters at registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'ben shaw',
        'email' => 'x@example.com',
        'password' => 'P@ssword1',
        'password_confirmation' => 'P@ssword1',
    ])->assertSessionHasErrors('username');
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'P@ssword1',
        'password_confirmation' => 'P@ssword1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
