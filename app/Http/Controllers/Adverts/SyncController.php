<?php

namespace App\Http\Controllers\Adverts;

use App\Http\Controllers\Controller;
use App\Jobs\SyncKolesaAdvertsJob;
use App\Models\KolesaSetting;
use App\Models\KolesaSyncRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SyncController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if (! KolesaSetting::current()->hasToken()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Сначала добавьте X-AUTH-TOKEN в настройках.'),
            ]);

            return to_route('kolesa.edit');
        }

        $current = KolesaSyncRun::latestRun();

        if ($current && in_array($current->status, [
            KolesaSyncRun::STATUS_PENDING,
            KolesaSyncRun::STATUS_RUNNING,
        ], true)) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Синхронизация уже выполняется.'),
            ]);

            return back();
        }

        $run = KolesaSyncRun::create([
            'status' => KolesaSyncRun::STATUS_PENDING,
            'triggered_by_user_id' => $request->user()?->id,
        ]);

        SyncKolesaAdvertsJob::dispatch($run->id)->onQueue(config('kolesa.queue_name'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Синхронизация запущена.'),
        ]);

        return to_route('adverts.index');
    }
}
