<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen cannot be rendered without invitation', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(403);
});

test('registration screen can be rendered with valid invitation', function () {
    $response = $this->get(route('register', ['invitation' => 'join-us']));

    $response->assertOk();
});

test('new users cannot register without invitation', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['invitation']);
    $this->assertGuest();
});

test('new users can register with valid invitation', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => 'join-us',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});