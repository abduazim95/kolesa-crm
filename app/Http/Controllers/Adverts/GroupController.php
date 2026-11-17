<?php

namespace App\Http\Controllers\Adverts;

use App\Http\Controllers\Controller;
use App\Models\Advert;
use App\Models\Group;
use App\Services\Kolesa\ServiceBatchLauncher;
use App\Services\Kolesa\ServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function __construct(
        private readonly ServiceCatalog $catalog,
        private readonly ServiceBatchLauncher $launcher,
    ) {
    }

    public function index(Request $request): Response
    {
        $groups = Group::query()
            ->withCount([
                'adverts',
                'adverts as live_count' => fn ($q) => $q->where('status', Advert::STATUS_LIVE),
                'adverts as archive_count' => fn ($q) => $q->where('status', Advert::STATUS_ARCHIVE),
            ])
            ->with('schedules:id,group_id,service_name,enabled')
            ->orderBy('name')
            ->get()
            ->map(fn (Group $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'adverts_count' => $g->adverts_count,
                'live_count' => $g->live_count,
                'archive_count' => $g->archive_count,
                'schedules_count' => $g->schedules->count(),
                'enabled_schedules' => $g->schedules->where('enabled', true)->count(),
            ]);

        return Inertia::render('Adverts/Groups/Index', [
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
        ]);

        $group = Group::firstOrCreate(
            ['name' => $validated['name']],
            ['created_by_user_id' => $request->user()->id],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Группа создана.'),
        ]);

        return to_route('adverts.groups.show', ['group' => $group->id]);
    }

    public function show(Request $request, Group $group): Response
    {
        $group->load([
            'adverts' => fn ($q) => $q->orderByDesc('published_at')->orderByDesc('id'),
            'schedules.createdBy:id,name',
        ]);

        return Inertia::render('Adverts/Groups/Show', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'created_at' => $group->created_at?->toIso8601String(),
            ],
            'adverts' => $group->adverts->map(fn (Advert $a) => [
                'id' => $a->id,
                'kolesa_id' => $a->kolesa_id,
                'title' => $a->title,
                'status' => $a->status,
                'photo_url' => $a->photo_url,
                'price' => $a->price,
                'show_till' => $a->show_till?->toIso8601String(),
                'active_services' => $a->active_services ?? [],
                'services' => $this->catalog->forAdvert($a),
            ])->values(),
            'available_services' => $this->catalog->allServices(),
            'schedules' => $group->schedules->map(fn ($s) => [
                'id' => $s->id,
                'service_name' => $s->service_name,
                'service_label' => $this->catalog->label($s->service_name),
                'service_storage' => $this->catalog->storageForService($s->service_name),
                'cron_expression' => $s->cron_expression,
                'timezone' => $s->timezone,
                'enabled' => $s->enabled,
                'last_run_at' => $s->last_run_at?->toIso8601String(),
                'next_run_at' => $s->enabled ? $s->nextRunAt()?->toIso8601String() : null,
                'last_batch_id' => $s->last_batch_id,
                'created_by' => $s->createdBy?->name,
            ])->values(),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
        ]);

        $group->update(['name' => $validated['name']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Группа переименована.')]);

        return back();
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        $group->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Группа удалена.')]);

        return to_route('adverts.groups.index');
    }

    public function addItems(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'advert_ids' => ['required', 'array', 'min:1', 'max:500'],
            'advert_ids.*' => ['integer'],
        ]);

        $adverts = Advert::query()
            ->whereIn('id', $validated['advert_ids'])
            ->pluck('id')
            ->all();

        $added = 0;
        $existing = $group->adverts()->pluck('adverts.id')->all();

        foreach ($adverts as $advertId) {
            if (! in_array($advertId, $existing, true)) {
                $group->adverts()->attach($advertId);
                $added++;
            }
        }

        Inertia::flash('toast', [
            'type' => $added > 0 ? 'success' : 'info',
            'message' => __('Добавлено в группу: :count.', ['count' => $added]),
        ]);

        return back();
    }

    public function removeItem(Request $request, Group $group, Advert $advert): RedirectResponse
    {
        $group->adverts()->detach($advert->id);

        return back();
    }

    public function clear(Request $request, Group $group): RedirectResponse
    {
        $group->adverts()->detach();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Группа очищена.')]);

        return back();
    }

    public function apply(Request $request, Group $group): RedirectResponse
    {
        $validated = $request->validate([
            'service_name' => ['required', 'string', 'max:32'],
        ]);
        $serviceName = $validated['service_name'];

        $storageId = $this->catalog->storageForService($serviceName);

        if ($storageId === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Неизвестная услуга.'),
            ]);

            return back();
        }

        $group->load('adverts');

        $batch = $this->launcher->launch(
            $group->adverts,
            $storageId,
            $serviceName,
            $request->user()->id,
            "group-{$group->id}-{$serviceName}-".now()->format('YmdHis'),
        );

        if ($batch === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('В группе нет подходящих объявлений для этой услуги.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Применение запущено для :count объявлений.', [
                'count' => $batch->totalJobs,
            ]),
        ]);

        return to_route('adverts.services.show', ['batch' => $batch->id]);
    }
}
