<?php

use App\Http\Controllers\Adverts\CartController;
use App\Http\Controllers\Adverts\IndexController;
use App\Http\Controllers\Adverts\ServiceBatchController;
use App\Http\Controllers\Adverts\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('adverts', IndexController::class)->name('adverts.index');
    Route::post('adverts/sync', [SyncController::class, 'store'])->name('adverts.sync.store');

    Route::get('adverts/cart', [CartController::class, 'index'])->name('adverts.cart.index');
    Route::post('adverts/cart', [CartController::class, 'store'])->name('adverts.cart.store');
    Route::delete('adverts/cart/{cart}/items/{item}', [CartController::class, 'destroyItem'])
        ->name('adverts.cart.item.destroy');
    Route::delete('adverts/cart/{cart}', [CartController::class, 'clear'])
        ->name('adverts.cart.clear');
    Route::post('adverts/cart/{cart}/checkout', [CartController::class, 'checkout'])
        ->name('adverts.cart.checkout');

    Route::get('adverts/services', [ServiceBatchController::class, 'index'])
        ->name('adverts.services.index');
    Route::get('adverts/services/{batch}', [ServiceBatchController::class, 'show'])
        ->name('adverts.services.show');
    Route::post('adverts/services/{batch}/cancel', [ServiceBatchController::class, 'cancel'])
        ->name('adverts.services.cancel');
});
