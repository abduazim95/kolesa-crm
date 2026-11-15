<?php

namespace App\Http\Controllers\Adverts;

use App\Http\Controllers\Controller;
use App\Models\ServiceApplication;
use App\Models\ServiceBatch;
use App\Services\Kolesa\ServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

class ServiceBatchController extends Controller
{
    public function __construct(private readonly ServiceCatalog $catalog)
    {
    }

    public function index(Request $request): Response
    {
        $batches = ServiceBatch::query()
            ->orderByDesc('created_at')
            ->paginate(30)
            ->through(fn (ServiceBatch $b) => [
                'id' => $b->id,
                'service_name' => $b->service_name,
                'service_label' => $this->catalog->label($b->service_name),
                'storage_id' => $b->storage_id,
                'total_count' => $b->total_count,
                'applied_count' => $b->applied_count,
                'failed_count' => $b->failed_count,
                'skipped_count' => $b->skipped_count,
                'estimated_total' => $b->estimated_total,
                'finished_at' => $b->finished_at?->toIso8601String(),
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Adverts/ServicesHistory', [
            'batches' => $batches,
        ]);
    }

    public function cancel(Request $request, string $batch): RedirectResponse
    {
        $model = ServiceBatch::query()->findOrFail($batch);

        abort_unless(
            $model->user_id === null || $model->user_id === $request->user()->id,
            403,
        );

        if ($model->finished_at !== null) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Batch уже завершён.'),
            ]);

            return back();
        }

        Bus::findBatch($batch)?->cancel();

        $skipped = (int) ServiceApplication::query()
            ->where('batch_id', $batch)
            ->where('status', ServiceApplication::STATUS_PENDING)
            ->update([
                'status' => ServiceApplication::STATUS_SKIPPED,
                'error_type' => ServiceApplication::ERR_CANCELLED,
                'error_message' => __('Отменено пользователем'),
            ]);

        $model->refresh();
        $model->update([
            'skipped_count' => $model->skipped_count + $skipped,
            'finished_at' => Carbon::now(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Применение отменено. Пропущено: :count.', ['count' => $skipped]),
        ]);

        return back();
    }

    public function show(string $batch): Response
    {
        $model = ServiceBatch::query()->findOrFail($batch);
        $laravelBatch = Bus::findBatch($batch);

        $applications = ServiceApplication::query()
            ->where('batch_id', $batch)
            ->with('advert:id,kolesa_id,title,status')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ServiceApplication $a) => [
                'id' => $a->id,
                'kolesa_id' => $a->kolesa_id,
                'advert_title' => $a->advert?->title,
                'advert_status' => $a->advert?->status,
                'service_name' => $a->service_name,
                'estimated_price' => $a->estimated_price,
                'status' => $a->status,
                'error_type' => $a->error_type,
                'error_message' => $a->error_message,
                'applied_at' => $a->applied_at?->toIso8601String(),
            ]);

        return Inertia::render('Adverts/ServiceBatch', [
            'batch' => [
                'id' => $model->id,
                'service_name' => $model->service_name,
                'service_label' => $this->catalog->label($model->service_name),
                'storage_id' => $model->storage_id,
                'total_count' => $model->total_count,
                'applied_count' => $model->applied_count,
                'failed_count' => $model->failed_count,
                'skipped_count' => $model->skipped_count,
                'pending_count' => max(
                    0,
                    $model->total_count
                    - $model->applied_count
                    - $model->failed_count
                    - $model->skipped_count,
                ),
                'estimated_total' => $model->estimated_total,
                'finished' => $model->finished_at !== null
                    || ($laravelBatch?->finished() ?? false),
                'cancelled' => $laravelBatch?->cancelled() ?? false,
                'progress' => $laravelBatch?->progress()
                    ?? (int) round(
                        ($model->applied_count + $model->failed_count + $model->skipped_count)
                        / max(1, $model->total_count) * 100,
                    ),
                'created_at' => $model->created_at?->toIso8601String(),
                'finished_at' => $model->finished_at?->toIso8601String(),
            ],
            'applications' => $applications,
        ]);
    }
}
