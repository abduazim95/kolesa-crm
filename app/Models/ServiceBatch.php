<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $storage_id
 * @property string $service_name
 * @property int $total_count
 * @property int $estimated_total
 * @property int $applied_count
 * @property int $failed_count
 * @property int $skipped_count
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'id',
    'user_id',
    'storage_id',
    'service_name',
    'total_count',
    'estimated_total',
    'applied_count',
    'failed_count',
    'skipped_count',
    'finished_at',
])]
class ServiceBatch extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ServiceApplication::class, 'batch_id');
    }

    protected function casts(): array
    {
        return [
            'total_count' => 'integer',
            'estimated_total' => 'integer',
            'applied_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'finished_at' => 'datetime',
        ];
    }
}
