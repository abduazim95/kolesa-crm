<?php

use App\Jobs\SyncKolesaAdvertsJob;
use App\Models\KolesaSetting;
use App\Models\KolesaSyncRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('sync endpoint refuses without token', function () {
    Queue::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('adverts.sync.store'))
        ->assertRedirect(route('kolesa.edit'));

    expect(KolesaSyncRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('sync endpoint dispatches job and creates sync run', function () {
    Queue::fake();

    $setting = KolesaSetting::current();
    $setting->token = 'tok-abcdef-1234';
    $setting->save();

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('adverts.sync.store'))
        ->assertRedirect(route('adverts.index'));

    expect(KolesaSyncRun::count())->toBe(1);
    expect(KolesaSyncRun::first()->status)->toBe('pending');

    Queue::assertPushedOn('kolesa-sync', SyncKolesaAdvertsJob::class);
});

test('sync endpoint refuses when run is already active', function () {
    Queue::fake();

    $setting = KolesaSetting::current();
    $setting->token = 'tok-abcdef-1234';
    $setting->save();

    KolesaSyncRun::create(['status' => KolesaSyncRun::STATUS_RUNNING]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->from(route('adverts.index'))
        ->post(route('adverts.sync.store'))
        ->assertRedirect(route('adverts.index'));

    expect(KolesaSyncRun::count())->toBe(1);
    Queue::assertNothingPushed();
});
