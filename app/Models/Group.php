<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'created_by_user_id'])]
class Group extends Model
{
    protected $table = 'groups';

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function adverts(): BelongsToMany
    {
        return $this->belongsToMany(Advert::class, 'group_items')
            ->withTimestamps();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(GroupSchedule::class);
    }
}
