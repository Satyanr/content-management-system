<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login');
});

test('users can authenticate using the login screen', function () {
    $user = dashboardUser();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});

test('navigation menu can be rendered', function () {
    $user = dashboardUser();

    $this->actingAs($user);

    $response = $this->get(route('admin.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Profil saya');
});

test('users without dashboard permission are directed to a safe status page', function () {
    $user = User::factory()->create();
    Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login')
        ->assertHasNoErrors()->assertRedirect(route('account.pending', absolute: false));
    $this->get(route('account.pending'))->assertOk()->assertSee('Akun menunggu akses');
    $this->get(route('admin.dashboard'))->assertForbidden();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('layout.navigation');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
});
