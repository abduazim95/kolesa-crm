<?php

namespace App\Jobs;

use App\Models\KolesaSyncRun;
use App\Services\Kolesa\KolesaSyncService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Throwable;

class SyncKolesaAdvertsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $runId)
    {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('kolesa-sync'))
                ->expireAfter(7200)
                ->dontRelease(),
        ];
    }

    public function uniqueId(): string
    {
        return 'kolesa-sync';
    }

    public function handle(KolesaSyncService $service): void
    {
        $run = KolesaSyncRun::findOrFail($this->runId);

        $service->run($run);
    }

    public function failed(Throwable $e): void
    {
        KolesaSyncRun::query()
            ->where('id', $this->runId)
            ->where('status', '!=', KolesaSyncRun::STATUS_COMPLETED)
            ->update([
                'status' => KolesaSyncRun::STATUS_FAILED,
                'error_type' => KolesaSyncRun::ERR_UNKNOWN,
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
                'current_section' => null,
            ]);
    }
}
