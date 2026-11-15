<?php

use App\Exceptions\Kolesa\KolesaApiException;
use App\Exceptions\Kolesa\KolesaServiceException;
use App\Models\KolesaSetting;
use App\Services\Kolesa\KolesaClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $setting = KolesaSetting::current();
    $setting->token = 'test-token-xyz';
    $setting->save();
});

test('setService returns body on status ok', function () {
    Http::fake([
        'api.kolesa.kz/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $result = app(KolesaClient::class)->setService(123, 'live', 'turbo-sale');

    expect($result)->toBe(['status' => 'ok']);
});

test('setService throws KolesaServiceException with mapped errorType on 101 insufficient funds', function () {
    Http::fake([
        'api.kolesa.kz/*' => Http::response([
            'error_code' => 101,
            'error' => 'Недостаточно средств на счёте',
        ], 200),
    ]);

    try {
        app(KolesaClient::class)->setService(123, 'live', 'turbo-sale');
        expect(false)->toBeTrue('expected KolesaServiceException');
    } catch (KolesaServiceException $e) {
        expect($e->errorCode)->toBe(101);
        expect($e->errorType())->toBe('insufficient_funds');
        expect($e->getMessage())->toContain('Недостаточно');
    }
});

test('setService maps 178 duplicate_operation', function () {
    Http::fake([
        'api.kolesa.kz/*' => Http::response([
            'error_code' => 178,
            'error' => 'duplicate',
        ], 200),
    ]);

    try {
        app(KolesaClient::class)->setService(123, 'live', 'vip');
        expect(false)->toBeTrue();
    } catch (KolesaServiceException $e) {
        expect($e->errorType())->toBe('duplicate_operation');
    }
});

test('setService throws KolesaApiException on unexpected shape', function () {
    Http::fake([
        'api.kolesa.kz/*' => Http::response(['foo' => 'bar'], 200),
    ]);

    app(KolesaClient::class)->setService(123, 'live', 'vip');
})->throws(KolesaApiException::class);
