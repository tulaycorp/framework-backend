<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// User authentication endpoints
Route::prefix('users')->group(function () {
    Route::post('/login', [UserController::class, 'login'])->name('api.users.login');
    Route::post('/signup', [UserController::class, 'signup'])->name('api.users.signup');
    Route::get('/check-email', [UserController::class, 'checkEmail'])->name('api.users.check-email');
    Route::post('/logout', [UserController::class, 'logout'])->name('api.users.logout');
    Route::get('/profile', [UserController::class, 'profile'])->name('api.users.profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('api.users.update-profile');
    Route::post('/change-password', [UserController::class, 'changePassword'])->name('api.users.change-password');
    
    // Orders
    Route::get('/{id}/orders', [\App\Http\Controllers\Api\OrderController::class, 'list'])->name('api.users.orders');
});

// Product endpoints
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'list'])->name('api.products.list');
    Route::get('/featured', [ProductController::class, 'featured'])->name('api.products.featured');
    Route::get('/categories', [ProductController::class, 'categories'])->name('api.products.categories');
    Route::get('/{id}', [ProductController::class, 'get'])->name('api.products.get');
});

// Coupon endpoints
Route::prefix('coupons')->group(function () {
    Route::post('/verify', [\App\Http\Controllers\Api\CouponController::class, 'verify'])->name('api.coupons.verify');
});

// Cart endpoints
Route::prefix('cart')->group(function () {
    Route::get('/data', [CartController::class, 'index'])->name('api.cart.data');
    Route::post('/sync', [CartController::class, 'sync'])->name('api.cart.sync');
    Route::post('/guest/reset', [CartController::class, 'resetGuest'])->name('api.cart.reset-guest');
});

// Checkout endpoints
Route::post('/checkout/process', [\App\Http\Controllers\Api\CheckoutController::class, 'process'])->name('api.checkout.process');

// Legacy action-based API support (for compatibility with existing frontend)
Route::get('/users.php', function (\Illuminate\Http\Request $request) {
    $action = $request->query('action');
    return match($action) {
        'check-email' => app(UserController::class)->checkEmail($request),
        default => response()->json(['error' => 'Invalid action'], 400),
    };
});

Route::post('/users.php', function (\Illuminate\Http\Request $request) {
    $action = $request->query('action');
    return match($action) {
        'login' => app(UserController::class)->login($request),
        'signup' => app(UserController::class)->signup($request),
        'logout' => app(UserController::class)->logout($request),
        default => response()->json(['error' => 'Invalid action'], 400),
    };
});

Route::get('/products.php', function (\Illuminate\Http\Request $request) {
    $action = $request->query('action', 'list');
    return match($action) {
        'list' => app(ProductController::class)->list($request),
        'get' => app(ProductController::class)->get($request->query('id', '')),
        'featured' => app(ProductController::class)->featured($request),
        'categories' => app(ProductController::class)->categories(),
        default => response()->json(['error' => 'Invalid action'], 400),
    };
});

// Include admin API routes
require __DIR__.'/api_admin.php';

