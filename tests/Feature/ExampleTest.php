<?php

use App\Models\User;

test('the home route redirects guests to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('the home route redirects authenticated users to the login page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('home'))->assertRedirect(route('login'));
});
