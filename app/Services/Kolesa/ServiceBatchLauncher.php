<?php

namespace App\Services\Kolesa;

use App\Jobs\ApplyAdvertServiceJob;
use App\Models\Advert;
use App\Models\ServiceApplication;
use App\Models\ServiceBatch;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ServiceBatchLauncher
{
    public function __construct(private readonly ServiceCatalog $catalog)
    {
    }

    /**
     * Create ServiceBatch + ServiceApplications for every applicable advert,
     * dispatch Bus::batch, and return the Laravel batch.
     *
     * Adverts without a price for the requested service, or whose storage no
     * longer matches, are silently filtered out.
     *
     * @param  Collection<int, Advert>  $adverts
     */
    public function launch(
        Collection $adverts,
        string $storageId,
        string $serviceName,
        ?int $triggeredByUserId,
        ?string $batchName = null,
    ): ?Batch {
        if (! $this->catalog->isAllowedFor($storageId, $serviceName)) {
            throw new RuntimeException("Service {$serviceName} not allowed for storage {$storageId}");
        }

        $plan = $adverts
            ->filter(fn (Advert $a) => $a->status === $storageId)
            ->map(function (Advert $advert) use ($serviceName) {
                $price = $this->catalog->priceFor($advert, $serviceName);

                return $price === null
                    ? null
                    : [
                        'advert_id' => $advert->id,
                        'kolesa_id' => $advert->kolesa_id,
                        'price' => $price,
                    ];
            })
            ->filter()
            ->values();

        if ($plan->isEmpty()) {
            return null;
        }

        $total = (int) $plan->sum('price');
        $provisionalId = (string) Str::uuid();
        $applicationIds = [];

        DB::transaction(function () use (
            $provisionalId,
            $storageId,
            $serviceName,
            $triggeredByUserId,
            $plan,
            $total,
            &$applicationIds,
        ) {
            ServiceBatch::create([
                'id' => $provisionalId,
                'user_id' => $triggeredByUserId,
                'storage_id' => $storageId,
                'service_name' => $serviceName,
                'total_count' => $plan->count(),
                'estimated_total' => $total,
            ]);

            foreach ($plan as $entry) {
                $app = ServiceApplication::create([
                    'batch_id' => $provisionalId,
                    'advert_id' => $entry['advert_id'],
                    'kolesa_id' => $entry['kolesa_id'],
                    'service_name' => $serviceName,
                    'storage_id' => $storageId,
                    'estimated_price' => $entry['price'],
                    'status' => ServiceApplication::STATUS_PENDING,
                ]);

                $applicationIds[] = $app->id;
            }
        });

        $jobs = array_map(
            fn (int $id) => new ApplyAdvertServiceJob($id),
            $applicationIds,
        );

        $batch = Bus::batch($jobs)
            ->name($batchName ?? "apply-{$serviceName}-".now()->format('YmdHis'))
            ->onQueue(config('kolesa.queue_packages'))
            ->allowFailures()
            ->dispatch();

        DB::transaction(function () use ($provisionalId, $batch) {
            ServiceApplication::query()
                ->where('batch_id', $provisionalId)
                ->update(['batch_id' => $batch->id]);
            ServiceBatch::query()
                ->whereKey($provisionalId)
                ->update(['id' => $batch->id]);
        });

        return $batch;
    }
}
