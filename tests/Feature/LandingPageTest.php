<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public landing page with unavailable download by default', function () {
    config(['player.windows_download_url' => null]);
    $this->get('/')->assertOk()->assertSee('Digital Signage Management Platform')->assertSee('Download belum tersedia')->assertSee('Windows 10 / 11');
});

it('only displays a configured valid HTTPS download URL', function ($url, $available) {
    config(['player.windows_download_url' => $url]);
    $response = $this->get('/')->assertOk();
    if ($available) {
        $response->assertSee('href="https://example.com/player.exe"', false)->assertDontSee('Download belum tersedia');
    } else {
        $response->assertSee('Download belum tersedia')->assertDontSee('Download Windows Player');
    }
})->with([
    ['https://example.com/player.exe', true],
    ['http://example.com/player.exe', false],
    ['javascript:alert(1)', false],
    ['https://user:pass@example.com/player.exe', false],
    ['not a url', false],
]);

it('links authenticated users to their permitted landing route', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/')->assertOk()->assertSee('href="'.route('account.pending').'"', false)->assertSee('Status Akun');
});
