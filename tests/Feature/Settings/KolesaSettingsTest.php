<?php

use App\Models\KolesaSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('kolesa settings page renders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('kolesa.edit'))
        ->assertOk();
});

test('token is stored encrypted and round-trips through the model', function () {
    $user = User::factory()->create();
    $plaintext = 'secret-xauth-token-9999';

    $this->actingAs($user)
        ->patch(route('kolesa.update'), ['token' => $plaintext])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kolesa.edit'));

    $raw = DB::table('kolesa_settings')->first();

    expect($raw->token)->not->toBe($plaintext);
    expect(KolesaSetting::current()->token)->toBe($plaintext);
    expect(KolesaSetting::current()->hasToken())->toBeTrue();
});

test('empty token is rejected by validation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('kolesa.edit'))
        ->patch(route('kolesa.update'), ['token' => ''])
        ->assertSessionHasErrors('token');
});
