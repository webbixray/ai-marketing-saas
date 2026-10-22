<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\Authenticate;

// Test route with standard Laravel sanctum middleware
Route::middleware('auth:sanctum')->get('/test-sanctum', function () {
    return response()->json([
        'user' => auth()->user(),
        'message' => 'Standard sanctum middleware works'
    ]);
});

// Test route with our custom middleware
Route::middleware([App\Http\Middleware\Authenticate::class . ':sanctum'])->get('/test-custom', function () {
    return response()->json([
        'user' => auth()->user(),
        'message' => 'Custom middleware works'
    ]);
});

// Test route with no middleware
Route::get('/test-no-middleware', function () {
    return response()->json([
        'user' => auth()->user(),
        'message' => 'No middleware'
    ]);
});
