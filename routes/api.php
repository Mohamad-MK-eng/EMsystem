<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BenchmarkController;
use App\Http\Controllers\CacheController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ProductAdminController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesReportController;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;


Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'login'])
    ->middleware('guest')
    ->name('login');



Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn(Request $request) => $request->user());

    Route::post('/logout', [AuthenticatedSessionController::class, 'logout'])
        ->name('logout');

    Route::get('/products/{id}',[ProductController::class, 'show']);  // ← جديد

    Route::get('/cart',                        [CartController::class, 'show']);
    Route::post('/cart/items',                 [CartController::class, 'addItem']);
    Route::patch('/cart/items/{cartItem}',     [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{cartItem}',    [CartController::class, 'removeItem']);

    Route::middleware('throttle:checkout')
        ->post('/checkout', [CheckoutController::class, 'store'])
        ->name('checkout');
});

Route::middleware('throttle:products')->get('/products',[ProductController::class, 'index']);


Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::post('/products',                        [ProductAdminController::class, 'store']);
        Route::put('/products/{product}',               [ProductAdminController::class, 'update']);
        Route::delete('/products/{product}',            [ProductAdminController::class, 'destroy']);
        Route::patch('/products/{product}/toggle-status',[ProductAdminController::class, 'toggleStatus']);

        Route::get('reports/summary',        [SalesReportController::class, 'summary']);
        Route::post('reports/retry-failed',  [SalesReportController::class, 'retryFailed']);
        Route::post('reports/generate',      [SalesReportController::class, 'generate']);
        Route::get('reports/{date}',         [SalesReportController::class, 'show']);
        Route::get('reports',                [SalesReportController::class, 'index']);

        Route::prefix('benchmark')->group(function () {
            Route::get('status',      [BenchmarkController::class, 'status']);
            Route::get('full',        [BenchmarkController::class, 'full']);
            Route::get('eager',       [BenchmarkController::class, 'eager']);
            Route::get('race',        [BenchmarkController::class, 'race']);
            Route::get('transaction', [BenchmarkController::class, 'transaction']);
            Route::get('async',       [BenchmarkController::class, 'async']);
        });

        Route::prefix('cache')->group(function () {
            Route::get('test',      [CacheController::class, 'test']);
            Route::post('flush',    [CacheController::class, 'flush']);
            Route::get('compare', [CacheController::class, 'compare']);
            Route::get('status', [CacheController::class, 'stats']);
        });
    });
