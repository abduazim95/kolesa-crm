<?php

namespace App\Console\Commands;

use App\Models\GroupSchedule;
use App\Services\Kolesa\ServiceBatchLauncher;
use App\Services\Kolesa\ServiceCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunGroupSchedules extends Command
{
    protected $signature = 'kolesa:run-group-schedules';

    protected $description = 'Fire any due group schedules — launches a service application batch for each.';

    public function handle(ServiceBatchLauncher $launcher, ServiceCatalog $catalog): int
    {
        $now = Carbon::now('UTC');
        $minuteStart = $now->copy()->startOfMinute();

        $schedules = GroupSchedule::query()
            ->where('enabled', true)
            ->where(function ($q) use ($minuteStart) {
                $q->whereNull('last_run_at')
                    ->orWhere('last_run_at', '<', $minuteStart);
            })
            ->with('group.adverts')
            ->get();

        $fired = 0;

        foreach ($schedules as $schedule) {
            if (! $schedule->isDue($now)) {
                continue;
            }

            if ($schedule->group === null) {
                continue;
            }

            $storageId = $catalog->storageForService($schedule->service_name);

            if ($storageId === null) {
                Log::warning('Group schedule skipped — unknown service', [
                    'schedule_id' => $schedule->id,
                    'service' => $schedule->service_name,
                ]);
                continue;
            }

            try {
                $batch = $launcher->launch(
                    $schedule->group->adverts,
                    $storageId,
                    $schedule->service_name,
                    $schedule->created_by_user_id,
                    "schedule-{$schedule->id}-".$now->format('YmdHis'),
                );

                $schedule->forceFill([
                    'last_run_at' => Carbon::now(),
                    'last_batch_id' => $batch?->id,
                ])->save();

                $fired++;

                $this->info(sprintf(
                    'Fired schedule #%d (group "%s", service %s): batch=%s',
                    $schedule->id,
                    $schedule->group->name,
                    $schedule->service_name,
                    $batch?->id ?? 'skipped (no applicable adverts)',
                ));
            } catch (Throwable $e) {
                Log::error('Group schedule failed to launch', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($fired > 0) {
            Log::info('Group schedules fired', ['count' => $fired]);
        }

        return self::SUCCESS;
    }
}
