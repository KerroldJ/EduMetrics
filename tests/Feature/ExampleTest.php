<?php

use App\Models\User;

test('guests are redirected from home to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirectToRoute('login');
});

test('authenticated users are redirected from home to the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirectToRoute('dashboard');
});
