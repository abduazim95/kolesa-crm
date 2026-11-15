<?php

namespace App\Services\Kolesa;

use App\Exceptions\Kolesa\KolesaApiException;
use App\Exceptions\Kolesa\KolesaAuthExpiredException;
use App\Exceptions\Kolesa\KolesaNetworkException;
use App\Exceptions\Kolesa\KolesaServiceException;
use App\Models\Advert;
use App\Models\ServiceApplication;
use App\Models\ServiceBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KolesaServiceApplicationService
{
    public function __construct(private readonly KolesaClient $client)
    {
    }

    public function apply(ServiceApplication $application): void
    {
        $application->refresh();

        if ($application->status !== ServiceApplication::STATUS_PENDING) {
            return;
        }

        $advert = $application->advert;

        if ($advert === null) {
            $this->fail(
                $application,
                ServiceApplication::ERR_INVALID_STATUS,
                'Advert not found',
            );

            return;
        }

        if ($advert->status !== $application->storage_id) {
            $this->fail(
                $application,
                ServiceApplication::ERR_INVALID_STATUS,
                "Advert storage changed to {$advert->status}",
                skipped: true,
            );

            return;
        }

        if (! in_array($advert->status, [Advert::STATUS_LIVE, Advert::STATUS_ARCHIVE], true)) {
            $this->fail(
                $application,
                ServiceApplication::ERR_INVALID_STATUS,
                "Advert status is {$advert->status}",
                skipped: true,
            );

            return;
        }

        $sleepMs = (int) config('kolesa.services_sleep_ms');

        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }

        try {
            $this->client->setService(
                $advert->kolesa_id,
                $advert->status,
                $application->service_name,
            );

            $application->update([
                'status' => ServiceApplication::STATUS_APPLIED,
                'applied_at' => Carbon::now(),
                'error_type' => null,
                'error_message' => null,
            ]);
        } catch (KolesaAuthExpiredException $e) {
            $this->fail($application, ServiceApplication::ERR_AUTH_EXPIRED, $e->getMessage());

            throw $e;
        } catch (KolesaServiceException $e) {
            $this->fail(
                $application,
                $e->errorType(),
                "[{$e->errorCode}] {$e->getMessage()}",
            );
        } catch (KolesaNetworkException $e) {
            $this->fail($application, ServiceApplication::ERR_NETWORK, $e->getMessage());
        } catch (KolesaApiException $e) {
            $this->fail($application, ServiceApplication::ERR_API, $e->getMessage());
        }
    }

    public function refreshBatchCounts(string $batchId): void
    {
        $counts = DB::table('service_applications')
            ->selectRaw('status, COUNT(*) as c')
            ->where('batch_id', $batchId)
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $updates = [
            'applied_count' => (int) ($counts[ServiceApplication::STATUS_APPLIED] ?? 0),
            'failed_count' => (int) ($counts[ServiceApplication::STATUS_FAILED] ?? 0),
            'skipped_count' => (int) ($counts[ServiceApplication::STATUS_SKIPPED] ?? 0),
        ];

        $pending = (int) ($counts[ServiceApplication::STATUS_PENDING] ?? 0);

        if ($pending === 0) {
            $updates['finished_at'] = Carbon::now();
        }

        ServiceBatch::query()->whereKey($batchId)->update($updates);
    }

    private function fail(
        ServiceApplication $application,
        string $errorType,
        string $message,
        bool $skipped = false,
    ): void {
        $application->update([
            'status' => $skipped
                ? ServiceApplication::STATUS_SKIPPED
                : ServiceApplication::STATUS_FAILED,
            'error_type' => $errorType,
            'error_message' => $message,
        ]);
    }
}
