<?php

use App\Exceptions\Kolesa\KolesaApiException;
use App\Exceptions\Kolesa\KolesaAuthExpiredException;
use App\Models\KolesaSetting;
use App\Services\Kolesa\KolesaClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $setting = KolesaSetting::current();
    $setting->token = 'test-token-123456';
    $setting->save();
});

test('listLive returns parsed json on 200', function () {
    Http::fake([
        'app.kolesa.kz/*' => Http::response(['items' => [['id' => 1]]], 200),
    ]);

    $result = app(KolesaClient::class)->listLive(0);

    expect($result)->toBe(['items' => [['id' => 1]]]);
});

test('listLive throws KolesaAuthExpiredException on 401', function () {
    Http::fake([
        'app.kolesa.kz/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    app(KolesaClient::class)->listLive(0);
})->throws(KolesaAuthExpiredException::class);

test('listLive throws KolesaApiException on 500', function () {
    Http::fake([
        'app.kolesa.kz/*' => Http::response(['error' => 'oops'], 500),
    ]);

    try {
        app(KolesaClient::class)->listLive(0);
        expect(false)->toBeTrue('expected KolesaApiException');
    } catch (KolesaApiException $e) {
        expect($e->statusCode)->toBe(500);
    }
});

test('request throws auth expired when token is empty', function () {
    KolesaSetting::query()->truncate();

    app(KolesaClient::class)->listLive(0);
})->throws(KolesaAuthExpiredException::class, 'Token not configured');

test('listArchive sends X-AUTH-TOKEN header', function () {
    Http::fake([
        'app.kolesa.kz/*' => Http::response(['items' => []], 200),
    ]);

    app(KolesaClient::class)->listArchive(20);

    Http::assertSent(fn ($request) =>
        $request->hasHeader('X-AUTH-TOKEN', 'test-token-123456')
        && str_contains($request->url(), '/user/adverts/archive')
        && str_contains($request->url(), 'offset=20')
    );
});
