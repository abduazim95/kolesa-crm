<?php

namespace App\Jobs;

use App\Exceptions\Kolesa\KolesaAuthExpiredException;
use App\Models\ServiceApplication;
use App\Services\Kolesa\KolesaServiceApplicationService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ApplyAdvertServiceJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $applicationId)
    {
    }

    public function handle(KolesaServiceApplicationService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $application = ServiceApplication::findOrFail($this->applicationId);

        try {
            $service->apply($application);
        } catch (KolesaAuthExpiredException $e) {
            $this->batch()?->cancel();

            throw $e;
        } finally {
            $service->refreshBatchCounts($application->batch_id);
        }
    }

    public function failed(Throwable $e): void
    {
        $app = ServiceApplication::query()->find($this->applicationId);

        if ($app === null) {
            return;
        }

        if ($app->status === ServiceApplication::STATUS_PENDING) {
            $app->update([
                'status' => ServiceApplication::STATUS_FAILED,
                'error_type' => ServiceApplication::ERR_UNKNOWN,
                'error_message' => $e->getMessage(),
            ]);
        }

        app(KolesaServiceApplicationService::class)->refreshBatchCounts($app->batch_id);
    }
}
