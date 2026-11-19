<?php

use App\Http\Controllers\Adverts\CartController;
use App\Http\Controllers\Adverts\ExportController;
use App\Http\Controllers\Adverts\GroupController;
use App\Http\Controllers\Adverts\GroupScheduleController;
use App\Http\Controllers\Adverts\IndexController;
use App\Http\Controllers\Adverts\ServiceBatchController;
use App\Http\Controllers\Adverts\SyncController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('adverts', IndexController::class)->name('adverts.index');
    Route::get('adverts/export', ExportController::class)->name('adverts.export');
    Route::post('adverts/sync', [SyncController::class, 'store'])->name('adverts.sync.store');

    Route::get('adverts/cart', [CartController::class, 'index'])->name('adverts.cart.index');
    Route::post('adverts/cart', [CartController::class, 'store'])->name('adverts.cart.store');
    Route::delete('adverts/cart/{cart}/items/{item}', [CartController::class, 'destroyItem'])
        ->name('adverts.cart.item.destroy');
    Route::delete('adverts/cart/{cart}', [CartController::class, 'clear'])
        ->name('adverts.cart.clear');
    Route::post('adverts/cart/{cart}/checkout', [CartController::class, 'checkout'])
        ->name('adverts.cart.checkout');

    Route::get('adverts/groups', [GroupController::class, 'index'])
        ->name('adverts.groups.index');
    Route::post('adverts/groups', [GroupController::class, 'store'])
        ->name('adverts.groups.store');
    Route::get('adverts/groups/{group}', [GroupController::class, 'show'])
        ->name('adverts.groups.show');
    Route::patch('adverts/groups/{group}', [GroupController::class, 'update'])
        ->name('adverts.groups.update');
    Route::delete('adverts/groups/{group}', [GroupController::class, 'destroy'])
        ->name('adverts.groups.destroy');
    Route::post('adverts/groups/{group}/items', [GroupController::class, 'addItems'])
        ->name('adverts.groups.items.store');
    Route::delete('adverts/groups/{group}/items/{advert}', [GroupController::class, 'removeItem'])
        ->name('adverts.groups.items.destroy');
    Route::delete('adverts/groups/{group}/items', [GroupController::class, 'clear'])
        ->name('adverts.groups.items.clear');
    Route::post('adverts/groups/{group}/apply', [GroupController::class, 'apply'])
        ->name('adverts.groups.apply');

    Route::post('adverts/groups/{group}/schedules', [GroupScheduleController::class, 'store'])
        ->name('adverts.groups.schedules.store');
    Route::patch('adverts/groups/{group}/schedules/{schedule}', [GroupScheduleController::class, 'update'])
        ->name('adverts.groups.schedules.update');
    Route::post('adverts/groups/{group}/schedules/{schedule}/toggle', [GroupScheduleController::class, 'toggle'])
        ->name('adverts.groups.schedules.toggle');
    Route::delete('adverts/groups/{group}/schedules/{schedule}', [GroupScheduleController::class, 'destroy'])
        ->name('adverts.groups.schedules.destroy');

    Route::get('adverts/services', [ServiceBatchController::class, 'index'])
        ->name('adverts.services.index');
    Route::get('adverts/services/{batch}', [ServiceBatchController::class, 'show'])
        ->name('adverts.services.show');
    Route::post('adverts/services/{batch}/cancel', [ServiceBatchController::class, 'cancel'])
        ->name('adverts.services.cancel');
});
