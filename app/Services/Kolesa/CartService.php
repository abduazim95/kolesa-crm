<?php

namespace App\Services\Kolesa;

use App\Models\Advert;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function findOrCreate(User $user, string $storageId): Cart
    {
        return Cart::firstOrCreate([
            'user_id' => $user->id,
            'storage_id' => $storageId,
        ]);
    }

    /**
     * Add adverts to a storage-specific cart. Mismatched storage and duplicates
     * are skipped silently.
     *
     * @param  array<int>  $advertIds
     * @return array{added: int, skipped: int, reasons: array<string, int>}
     */
    public function add(Cart $cart, array $advertIds): array
    {
        $adverts = Advert::query()->whereIn('id', $advertIds)->get();

        $reasons = ['wrong_storage' => 0, 'already_in_cart' => 0];
        $existing = $cart->items()->pluck('advert_id')->all();
        $added = 0;

        foreach ($adverts as $advert) {
            if ($advert->status !== $cart->storage_id) {
                $reasons['wrong_storage']++;

                continue;
            }

            if (in_array($advert->id, $existing, true)) {
                $reasons['already_in_cart']++;

                continue;
            }

            CartItem::create([
                'cart_id' => $cart->id,
                'advert_id' => $advert->id,
            ]);

            $added++;
        }

        return [
            'added' => $added,
            'skipped' => array_sum($reasons),
            'reasons' => $reasons,
        ];
    }

    public function removeItem(Cart $cart, int $cartItemId): void
    {
        $cart->items()->whereKey($cartItemId)->delete();
    }

    public function clear(Cart $cart): void
    {
        DB::transaction(function () use ($cart) {
            $cart->items()->delete();
            $cart->delete();
        });
    }

    /**
     * Drop items whose advert changed storage (no longer belongs in this cart).
     *
     * @return int number of removed items
     */
    public function prune(Cart $cart): int
    {
        $invalidIds = $cart->items()
            ->join('adverts', 'adverts.id', '=', 'cart_items.advert_id')
            ->where('adverts.status', '!=', $cart->storage_id)
            ->pluck('cart_items.id');

        if ($invalidIds->isEmpty()) {
            return 0;
        }

        return (int) $cart->items()->whereIn('id', $invalidIds)->delete();
    }
}
