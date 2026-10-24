<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Auth\WebAuthController;
use App\Http\Controllers\Admin\PlanController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return view('welcome');
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
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    
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

    // User management routes
    Route::get('/users', [App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [App\Http\Controllers\Admin\UserManagementController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/toggle-active', [App\Http\Controllers\Admin\UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    Route::post('/users/{user}/role', [App\Http\Controllers\Admin\UserManagementController::class, 'updateRole'])->name('users.role');
    
    Route::get('/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{notification}/mark-as-read', [App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.mark-as-read');
    Route::post('/notifications/mark-all-as-read', [App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-as-read');

    // Performance Monitoring
    Route::get('/monitoring', [App\Http\Controllers\MonitoringController::class, 'index'])->name('monitoring.index');
    Route::get('/monitoring/status', [App\Http\Controllers\MonitoringController::class, 'apiStatus'])->name('monitoring.status');
});
