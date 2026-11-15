<?php

use App\Models\Advert;
use App\Models\KolesaSetting;
use App\Models\KolesaSyncRun;
use App\Services\Kolesa\KolesaSyncService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['kolesa.sleep_seconds' => 0]);

    $setting = KolesaSetting::current();
    $setting->token = 'tok-sync-test';
    $setting->save();
});

test('sync service upserts adverts and marks missing as removed', function () {
    $buildItem = fn (int $i) => [
        'id' => $i,
        'data' => [
            'title' => "Live #$i",
            'price' => $i * 100,
            'add_date' => '2026-11-10T12:00:00+05:00',
        ],
        'storage_id' => 'live',
        'service_data' => [
            'live_at' => '2026-11-10T12:00:00+05:00',
        ],
    ];

    Http::fake([
        'app.kolesa.kz/*/user/adverts/live*' => Http::sequence()
            ->push([
                'total' => 21,
                'items' => array_map($buildItem, range(1, 20)),
            ], 200)
            ->push([
                'total' => 21,
                'items' => [$buildItem(21)],
            ], 200),
        'app.kolesa.kz/*/user/adverts/archive*' => Http::response(
            ['total' => 0, 'items' => []],
            200,
        ),
    ]);

    Advert::create([
        'kolesa_id' => 999,
        'title' => 'Will disappear',
        'status' => Advert::STATUS_LIVE,
        'last_seen_at' => now()->subDay(),
    ]);

    $run = KolesaSyncRun::create(['status' => KolesaSyncRun::STATUS_PENDING]);

    app(KolesaSyncService::class)->run($run);

    $run->refresh();

    expect($run->status)->toBe(KolesaSyncRun::STATUS_COMPLETED);
    expect($run->live_processed)->toBe(21);
    expect($run->archive_processed)->toBe(0);
    expect($run->marked_removed_count)->toBe(1);
    expect($run->live_total)->toBe(21);
    expect($run->error_type)->toBeNull();

    expect(Advert::where('kolesa_id', 999)->first()->status)
        ->toBe(Advert::STATUS_DELETE);
    expect(Advert::where('kolesa_id', 1)->first()->status)
        ->toBe(Advert::STATUS_LIVE);
    expect(Advert::where('kolesa_id', 21)->first()->title)
        ->toBe('Live #21');
    expect(Advert::where('kolesa_id', 1)->first()->price)->toBe(100);
});

test('sync service fails fast with auth_expired on 401', function () {
    Http::fake([
        'app.kolesa.kz/*' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    $run = KolesaSyncRun::create(['status' => KolesaSyncRun::STATUS_PENDING]);

    app(KolesaSyncService::class)->run($run);

    $run->refresh();

    expect($run->status)->toBe(KolesaSyncRun::STATUS_FAILED);
    expect($run->error_type)->toBe(KolesaSyncRun::ERR_AUTH_EXPIRED);
    expect($run->finished_at)->not->toBeNull();
});
