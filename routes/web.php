<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\Admin\PlanController;

Log::info('WEB.PHP ROUTES FILE LOADED');

Route::get('/', function () {
    Log::info('WEB ROOT ROUTE ACCESSSED');
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return view('welcome');
});

Route::get('/test-log', function () {
    Log::info('TEST LOG ROUTE ACCESSSED');
    return response()->json(['message' => 'log test']);
});

// Pulse dashboard is auto-registered at /pulse (configured in config/pulse.php)

// Override the Cashier webhook route to use our custom controller.
Route::post('stripe/webhook', [App\Http\Controllers\Api\StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook');

// Authentication routes
Route::get('/login', [App\Http\Controllers\Auth\WebAuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\WebAuthController::class, 'login']);
Route::get('/register', [App\Http\Controllers\Auth\WebAuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [App\Http\Controllers\Auth\WebAuthController::class, 'register']);
Route::post('/logout', [App\Http\Controllers\Auth\WebAuthController::class, 'logout'])->name('logout');

// Admin routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');
    
    // Plans routes
    Route::get('/plans', [App\Http\Controllers\Admin\PlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [App\Http\Controllers\Admin\PlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [App\Http\Controllers\Admin\PlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [App\Http\Controllers\Admin\PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [App\Http\Controllers\Admin\PlanController::class, 'update'])->name('plans.update');
    Route::delete('/plans/{plan}', [App\Http\Controllers\Admin\PlanController::class, 'destroy'])->name('plans.destroy');
    
    Route::get('/reports', function () {
        return view('admin.reports');
    })->name('reports');
    
    Route::get('/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{notification}/mark-as-read', [App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('/notifications/mark-all-as-read', [App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');
});
