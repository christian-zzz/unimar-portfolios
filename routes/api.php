<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\LighthouseController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AnalyticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api and protected by the api middleware
| group. Token-based authentication is handled via Laravel Sanctum.
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'env' => config('app.env'),
    ]);
});

// Public Authentication Route
Route::post('/login', [AuthController::class, 'login']);

// Protected API Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });

    // User Profile Routes
    Route::put('/user/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);

    // Students Management Routes
    Route::get('/students', [UserController::class, 'index']);
    Route::post('/students', [AuthController::class, 'registerStudent']);

    // Lighthouse Audit Routes
    Route::post('/audit/run', [LighthouseController::class, 'runAudit']);

    // Analytics GA4 Routes
    Route::get('/analytics/report', [AnalyticsController::class, 'getReport']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
