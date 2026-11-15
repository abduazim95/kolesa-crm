<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * @property int $id
 * @property string|null $token
 * @property Carbon|null $token_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['token_updated_at'])]
class KolesaSetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], ['token' => null]);
    }

    public function getTokenAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            Log::warning('KolesaSetting token decryption failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function setTokenAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['token'] = null;

            return;
        }

        $this->attributes['token'] = Crypt::encryptString($value);
    }

    public function hasToken(): bool
    {
        return ! empty($this->attributes['token'] ?? null);
    }

    protected function casts(): array
    {
        return [
            'token_updated_at' => 'datetime',
        ];
    }
}
