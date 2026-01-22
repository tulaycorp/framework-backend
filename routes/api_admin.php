<?php

use App\Http\Controllers\Admin\Api\AuthController;
use App\Http\Controllers\Admin\Api\CategoryController;
use App\Http\Controllers\Admin\Api\CustomerController;
use App\Http\Controllers\Admin\Api\DashboardController;
use App\Http\Controllers\Admin\Api\OrderController;
use App\Http\Controllers\Admin\Api\ProductController;

use App\Http\Controllers\Admin\Api\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| These routes are used by the admin panel to manage the store.
| All routes except auth are protected by the admin.api middleware.
|
*/

// Public admin routes (no auth required)
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('admin.api.login');
    Route::get('/settings', [SettingController::class, 'index'])->name('admin.api.settings.index');
});

// Protected admin routes
Route::prefix('admin')->middleware('admin.api')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('admin.api.me');
    
    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('admin.api.dashboard.stats');
    Route::get('/dashboard/recent-orders', [DashboardController::class, 'recentOrders'])->name('admin.api.dashboard.recent-orders');
    Route::get('/dashboard/revenue-chart', [DashboardController::class, 'revenueChart'])->name('admin.api.dashboard.revenue-chart');
    Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStockProducts'])->name('admin.api.dashboard.low-stock');
    
    // Categories
    Route::apiResource('categories', CategoryController::class)->names([
        'index' => 'admin.api.categories.index',
        'store' => 'admin.api.categories.store',
        'show' => 'admin.api.categories.show',
        'update' => 'admin.api.categories.update',
        'destroy' => 'admin.api.categories.destroy',
    ]);
    
    // Products
    Route::get('/products/categories', [ProductController::class, 'categories'])->name('admin.api.products.categories');
    Route::post('/products/presigned-upload-url', [ProductController::class, 'getPresignedUploadUrl'])->name('admin.api.products.presigned-upload-url');
    Route::post('/products/upload-image', [ProductController::class, 'uploadImage'])->name('admin.api.products.upload-image');
    Route::apiResource('products', ProductController::class)->names([
        'index' => 'admin.api.products.index',
        'store' => 'admin.api.products.store',
        'show' => 'admin.api.products.show',
        'update' => 'admin.api.products.update',
        'destroy' => 'admin.api.products.destroy',
    ]);
    
    // Orders
    Route::get('/orders/statuses', [OrderController::class, 'statuses'])->name('admin.api.orders.statuses');
    Route::apiResource('orders', OrderController::class)->names([
        'index' => 'admin.api.orders.index',
        'store' => 'admin.api.orders.store',
        'show' => 'admin.api.orders.show',
        'update' => 'admin.api.orders.update',
        'destroy' => 'admin.api.orders.destroy',
    ]);
    
    // Customers
    Route::apiResource('customers', CustomerController::class)->names([
        'index' => 'admin.api.customers.index',
        'store' => 'admin.api.customers.store',
        'show' => 'admin.api.customers.show',
        'update' => 'admin.api.customers.update',
        'destroy' => 'admin.api.customers.destroy',
    ]);

    // Coupons
    Route::get('/coupons/{id}/usages', [\App\Http\Controllers\Admin\Api\CouponController::class, 'usages'])->name('admin.api.coupons.usages');
    Route::apiResource('coupons', \App\Http\Controllers\Admin\Api\CouponController::class)->names([
        'index' => 'admin.api.coupons.index',
        'store' => 'admin.api.coupons.store',
        'show' => 'admin.api.coupons.show',
        'update' => 'admin.api.coupons.update',
        'destroy' => 'admin.api.coupons.destroy',
    ]);
    
    // Settings
    Route::post('/settings', [SettingController::class, 'update'])->name('admin.api.settings.update');
    Route::post('/settings/upload-image', [SettingController::class, 'uploadImage'])->name('admin.api.settings.upload-image');
});
