<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * @property int $id
 * @property int $group_id
 * @property string $service_name
 * @property string $cron_expression
 * @property string $timezone
 * @property bool $enabled
 * @property Carbon|null $last_run_at
 * @property string|null $last_batch_id
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'group_id',
    'service_name',
    'cron_expression',
    'timezone',
    'enabled',
    'last_run_at',
    'last_batch_id',
    'created_by_user_id',
])]
class GroupSchedule extends Model
{
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function nextRunAt(?Carbon $from = null): ?Carbon
    {
        try {
            $tz = $this->timezone ?: 'UTC';
            $from ??= Carbon::now($tz);
            $next = (new CronExpression($this->cron_expression))
                ->getNextRunDate($from->setTimezone($tz));

            return Carbon::instance($next)->setTimezone($tz);
        } catch (Throwable) {
            return null;
        }
    }

    public function isDue(?Carbon $at = null): bool
    {
        try {
            $tz = $this->timezone ?: 'UTC';
            $at ??= Carbon::now($tz);

            return (new CronExpression($this->cron_expression))
                ->isDue($at->setTimezone($tz));
        } catch (Throwable) {
            return false;
        }
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }
}
