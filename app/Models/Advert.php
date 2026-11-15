<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kolesa_id
 * @property string|null $title
 * @property int|null $price
 * @property string $status
 * @property string|null $photo_url
 * @property Carbon|null $published_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $removed_at
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'kolesa_id',
    'title',
    'price',
    'status',
    'photo_url',
    'views_total',
    'views_week',
    'phone_views_total',
    'phone_views_week',
    'favorites_total',
    'favorites_week',
    'active_services',
    'show_till',
    'published_at',
    'last_seen_at',
    'removed_at',
    'raw',
])]
class Advert extends Model
{
    public const STATUS_LIVE = 'live';
    public const STATUS_ARCHIVE = 'archive';
    public const STATUS_DELETE = 'delete';
    public const STATUS_TEMP = 'temp';

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'views_total' => 'integer',
            'views_week' => 'integer',
            'phone_views_total' => 'integer',
            'phone_views_week' => 'integer',
            'favorites_total' => 'integer',
            'favorites_week' => 'integer',
            'active_services' => 'array',
            'show_till' => 'datetime',
            'published_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'removed_at' => 'datetime',
            'raw' => 'array',
        ];
    }
}
