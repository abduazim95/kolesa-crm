<?php

namespace App\Http\Controllers\Adverts;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupSchedule;
use App\Services\Kolesa\ServiceCatalog;
use Cron\CronExpression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class GroupScheduleController extends Controller
{
    public function __construct(private readonly ServiceCatalog $catalog)
    {
    }

    public function store(Request $request, Group $group): RedirectResponse
    {
        $data = $this->validatePayload($request, $group);

        GroupSchedule::create([
            'group_id' => $group->id,
            'service_name' => $data['service_name'],
            'cron_expression' => $data['cron_expression'],
            'timezone' => $data['timezone'],
            'enabled' => $data['enabled'],
            'created_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Расписание добавлено.')]);

        return back();
    }

    public function update(Request $request, Group $group, GroupSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->group_id === $group->id, 404);

        $data = $this->validatePayload($request, $group);

        $schedule->update([
            'service_name' => $data['service_name'],
            'cron_expression' => $data['cron_expression'],
            'timezone' => $data['timezone'],
            'enabled' => $data['enabled'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Расписание обновлено.')]);

        return back();
    }

    public function destroy(Request $request, Group $group, GroupSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->group_id === $group->id, 404);

        $schedule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Расписание удалено.')]);

        return back();
    }

    public function toggle(Request $request, Group $group, GroupSchedule $schedule): RedirectResponse
    {
        abort_unless($schedule->group_id === $group->id, 404);

        $schedule->update(['enabled' => ! $schedule->enabled]);

        return back();
    }

    /**
     * @return array{service_name: string, cron_expression: string, timezone: string, enabled: bool}
     */
    private function validatePayload(Request $request, Group $group): array
    {
        $validated = $request->validate([
            'service_name' => ['required', 'string', 'max:32'],
            'cron_expression' => ['required', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        if ($this->catalog->storageForService($validated['service_name']) === null) {
            throw ValidationException::withMessages([
                'service_name' => __('Неизвестная услуга.'),
            ]);
        }

        try {
            new CronExpression($validated['cron_expression']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'cron_expression' => __('Некорректное cron-выражение: :msg', ['msg' => $e->getMessage()]),
            ]);
        }

        return [
            'service_name' => $validated['service_name'],
            'cron_expression' => trim($validated['cron_expression']),
            'timezone' => $validated['timezone'] ?? 'Asia/Almaty',
            'enabled' => (bool) ($validated['enabled'] ?? true),
        ];
    }
}
