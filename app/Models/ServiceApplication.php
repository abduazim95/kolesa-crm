<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $batch_id
 * @property int $advert_id
 * @property int $kolesa_id
 * @property string $service_name
 * @property string $storage_id
 * @property int $estimated_price
 * @property string $status
 * @property string|null $error_type
 * @property string|null $error_message
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'batch_id',
    'advert_id',
    'kolesa_id',
    'service_name',
    'storage_id',
    'estimated_price',
    'status',
    'error_type',
    'error_message',
    'applied_at',
])]
class ServiceApplication extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const ERR_AUTH_EXPIRED = 'auth_expired';
    public const ERR_NETWORK = 'network';
    public const ERR_API = 'api_error';
    public const ERR_INVALID_STATUS = 'invalid_status';
    public const ERR_CANCELLED = 'cancelled';
    public const ERR_UNKNOWN = 'unknown';

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ServiceBatch::class, 'batch_id');
    }

    public function advert(): BelongsTo
    {
        return $this->belongsTo(Advert::class);
    }

    protected function casts(): array
    {
        return [
            'estimated_price' => 'integer',
            'applied_at' => 'datetime',
        ];
    }
}
