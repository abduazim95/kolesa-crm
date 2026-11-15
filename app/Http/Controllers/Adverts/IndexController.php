<?php

namespace App\Http\Controllers\Adverts;

use App\Http\Controllers\Controller;
use App\Models\Advert;
use App\Models\KolesaSetting;
use App\Models\KolesaSyncRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __invoke(Request $request): Response
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

        $adverts = Advert::query()
            ->status($status)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', '%'.$q.'%');

                    if (ctype_digit($q)) {
                        $inner->orWhere('kolesa_id', (int) $q);
                    }
                });
            })
            ->when($service !== null && $service !== '', function ($query) use ($service) {
                $query->whereJsonContains('active_services', $service);
            })
            ->when($without !== null && $without !== '', function ($query) use ($without) {
                $query->where(function ($inner) use ($without) {
                    $inner->whereJsonDoesntContain('active_services', $without)
                        ->orWhereNull('active_services');
                });
            })
            ->orderBy($sortColumn, $direction)
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Advert $advert) => [
                'id' => $advert->id,
                'kolesa_id' => $advert->kolesa_id,
                'title' => $advert->title,
                'price' => $advert->price,
                'status' => $advert->status,
                'photo_url' => $advert->photo_url,
                'views_total' => $advert->views_total,
                'views_week' => $advert->views_week,
                'phone_views_total' => $advert->phone_views_total,
                'phone_views_week' => $advert->phone_views_week,
                'favorites_total' => $advert->favorites_total,
                'favorites_week' => $advert->favorites_week,
                'active_services' => $advert->active_services ?? [],
                'show_till' => $advert->show_till?->toIso8601String(),
                'published_at' => $advert->published_at?->toIso8601String(),
            ]);

        $run = KolesaSyncRun::latestRun();

        return Inertia::render('Adverts/Index', [
            'adverts' => $adverts,
            'filters' => [
                'status' => $status,
                'q' => $q !== '' ? $q : null,
                'sort' => $sort,
                'direction' => $direction,
                'service' => $service,
                'without' => $without,
            ],
            'syncRun' => $run ? [
                'id' => $run->id,
                'status' => $run->status,
                'current_section' => $run->current_section,
                'live_processed' => $run->live_processed,
                'archive_processed' => $run->archive_processed,
                'live_total' => $run->live_total,
                'archive_total' => $run->archive_total,
                'marked_removed_count' => $run->marked_removed_count,
                'error_type' => $run->error_type,
                'error_message' => $run->error_message,
                'started_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
            ] : null,
            'hasToken' => KolesaSetting::current()->hasToken(),
        ]);
    }
}
