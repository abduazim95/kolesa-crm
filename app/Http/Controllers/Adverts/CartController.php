<?php

namespace App\Http\Controllers\Adverts;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\Kolesa\CartService;
use App\Services\Kolesa\ServiceBatchLauncher;
use App\Services\Kolesa\ServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ServiceCatalog $catalog,
        private readonly ServiceBatchLauncher $launcher,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $carts = Cart::query()
            ->where('user_id', $user->id)
            ->with(['items.advert:id,kolesa_id,title,status,photo_url,raw'])
            ->orderBy('storage_id')
            ->get();

        $prunedTotal = 0;
        foreach ($carts as $cart) {
            $prunedTotal += $this->cartService->prune($cart);
        }

        if ($prunedTotal > 0) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __(
                    'Из корзины убрано :count объявлений — изменился статус на kolesa.kz.',
                    ['count' => $prunedTotal],
                ),
            ]);
        }

        $carts->each->refresh();
        $carts->load('items.advert:id,kolesa_id,title,status,photo_url,raw');

        return Inertia::render('Adverts/Cart', [
            'carts' => $carts->map(fn (Cart $cart) => $this->shapeCart($cart))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'storage_id' => ['required', 'string', 'in:live,archive'],
            'advert_ids' => ['required', 'array', 'min:1', 'max:500'],
            'advert_ids.*' => ['integer'],
        ]);

        $cart = $this->cartService->findOrCreate(
            $request->user(),
            $validated['storage_id'],
        );

        $result = $this->cartService->add($cart, $validated['advert_ids']);

        $parts = ["Добавлено: {$result['added']}"];

        if ($result['reasons']['already_in_cart'] > 0) {
            $parts[] = "уже в корзине: {$result['reasons']['already_in_cart']}";
        }
        if ($result['reasons']['wrong_storage'] > 0) {
            $parts[] = "не совпал статус: {$result['reasons']['wrong_storage']}";
        }

        Inertia::flash('toast', [
            'type' => $result['added'] > 0 ? 'success' : 'info',
            'message' => implode(', ', $parts),
        ]);

        return back();
    }

    public function destroyItem(Request $request, Cart $cart, int $item): RedirectResponse
    {
        abort_unless($cart->user_id === $request->user()->id, 403);

        $this->cartService->removeItem($cart, $item);

        return back();
    }

    public function clear(Request $request, Cart $cart): RedirectResponse
    {
        abort_unless($cart->user_id === $request->user()->id, 403);

        $this->cartService->clear($cart);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Корзина очищена.')]);

        return to_route('adverts.cart.index');
    }

    public function checkout(Request $request, Cart $cart): RedirectResponse
    {
        abort_unless($cart->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'service_name' => ['required', 'string', 'max:32'],
        ]);
        $serviceName = $validated['service_name'];

        if (! $this->catalog->isAllowedFor($cart->storage_id, $serviceName)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Услуга недоступна для этой корзины.'),
            ]);

            return back();
        }

        $this->cartService->prune($cart);
        $cart->load('items.advert');

        $adverts = $cart->items->map->advert->filter()->values();

        $batch = $this->launcher->launch(
            $adverts,
            $cart->storage_id,
            $serviceName,
            $request->user()->id,
        );

        if ($batch === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Нет объявлений с ценой на выбранную услугу.'),
            ]);

            return back();
        }

        $this->cartService->clear($cart);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Применение запущено для :count объявлений.', [
                'count' => $batch->totalJobs,
            ]),
        ]);

        return to_route('adverts.services.show', ['batch' => $batch->id]);
    }

    private function shapeCart(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'storage_id' => $cart->storage_id,
            'count' => $cart->items->count(),
            'items' => $cart->items->map(function ($item) {
                $advert = $item->advert;

                return [
                    'id' => $item->id,
                    'advert_id' => $item->advert_id,
                    'kolesa_id' => $advert?->kolesa_id,
                    'title' => $advert?->title,
                    'photo_url' => $advert?->photo_url,
                    'advert_status' => $advert?->status,
                    'services' => $advert
                        ? $this->catalog->forAdvert($advert)
                        : [],
                ];
            })->values(),
        ];
    }
}
