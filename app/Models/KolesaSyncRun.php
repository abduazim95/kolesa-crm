<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $status
 * @property string|null $current_section
 * @property int $live_processed
 * @property int $archive_processed
 * @property int|null $live_total
 * @property int|null $archive_total
 * @property int $live_offset
 * @property int $archive_offset
 * @property int $marked_removed_count
 * @property string|null $error_type
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $triggered_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'status',
    'current_section',
    'live_processed',
    'archive_processed',
    'live_total',
    'archive_total',
    'live_offset',
    'archive_offset',
    'marked_removed_count',
    'error_type',
    'error_message',
    'started_at',
    'finished_at',
    'triggered_by_user_id',
])]
class KolesaSyncRun extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const SECTION_LIVE = 'live';
    public const SECTION_ARCHIVE = 'archive';
    public const SECTION_FINALIZE = 'finalize';

    public const ERR_AUTH_EXPIRED = 'auth_expired';
    public const ERR_NETWORK = 'network';
    public const ERR_API = 'api_error';
    public const ERR_UNKNOWN = 'unknown';

    public static function latestRun(): ?self
    {
        return static::query()->latest('id')->first();
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'live_processed' => 'integer',
            'archive_processed' => 'integer',
            'live_total' => 'integer',
            'archive_total' => 'integer',
            'live_offset' => 'integer',
            'archive_offset' => 'integer',
            'marked_removed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
