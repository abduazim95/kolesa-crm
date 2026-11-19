<?php

namespace App\Http\Controllers\Adverts;

use App\Http\Controllers\Controller;
use App\Models\Advert;
use App\Services\Kolesa\ServiceCatalog;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(private readonly ServiceCatalog $catalog)
    {
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:live,archive'],
            'q' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', 'string', 'in:published_at,views,phone_views,favorites,show_till'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'service' => ['nullable', 'string', 'max:32'],
            'without' => ['nullable', 'string', 'max:32'],
        ]);

        $status = $validated['status'] ?? 'live';
        $q = trim($validated['q'] ?? '');
        $sort = $validated['sort'] ?? 'published_at';
        $direction = $validated['direction'] ?? 'desc';
        $service = $validated['service'] ?? null;
        $without = $validated['without'] ?? null;

        $sortColumn = match ($sort) {
            'views' => 'views_total',
            'phone_views' => 'phone_views_total',
            'favorites' => 'favorites_total',
            'show_till' => 'show_till',
            default => 'published_at',
        };

        $filename = sprintf(
            'adverts-%s-%s.xlsx',
            $status,
            now()->format('Y-m-d_H-i'),
        );

        return new StreamedResponse(function () use (
            $status, $q, $sortColumn, $direction, $service, $without,
        ) {
            $writer = new Writer(new Options());
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'ID на kolesa',
                'Название',
                'Цена',
                'Статус',
                'Просмотры (всего)',
                'Просмотры (неделя)',
                'Звонки (всего)',
                'Звонки (неделя)',
                'В избранном (всего)',
                'В избранном (неделя)',
                'Активные услуги',
                'Продвигается до',
                'Опубликовано',
                'Ссылка',
            ]));

            Advert::query()
                ->status($status)
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($inner) use ($q) {
                        $inner->where('title', 'like', '%'.$q.'%');
                        if (ctype_digit($q)) {
                            $inner->orWhere('kolesa_id', (int) $q);
                        }
                    });
                })
                ->when($service, fn ($query) => $query->whereJsonContains('active_services', $service))
                ->when($without, function ($query) use ($without) {
                    $query->where(function ($inner) use ($without) {
                        $inner->whereJsonDoesntContain('active_services', $without)
                            ->orWhereNull('active_services');
                    });
                })
                ->orderBy($sortColumn, $direction)
                ->orderByDesc('id')
                ->chunkById(500, function ($chunk) use ($writer) {
                    foreach ($chunk as $advert) {
                        $writer->addRow(Row::fromValues([
                            $advert->kolesa_id,
                            $advert->title ?? '',
                            $advert->price,
                            $this->statusLabel($advert->status),
                            $advert->views_total,
                            $advert->views_week,
                            $advert->phone_views_total,
                            $advert->phone_views_week,
                            $advert->favorites_total,
                            $advert->favorites_week,
                            $this->formatServices($advert->active_services ?? []),
                            $advert->show_till?->format('Y-m-d H:i'),
                            $advert->published_at?->format('Y-m-d H:i'),
                            'https://kolesa.kz/a/show/'.$advert->kolesa_id,
                        ]));
                    }
                });

            $writer->close();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Advert::STATUS_LIVE => 'Активное',
            Advert::STATUS_ARCHIVE => 'Архив',
            Advert::STATUS_DELETE => 'Удалено',
            Advert::STATUS_TEMP => 'Черновик',
            default => $status,
        };
    }

    private function formatServices(array $services): string
    {
        return collect($services)
            ->map(fn (string $name) => $this->catalog->label($name))
            ->implode(', ');
    }
}
