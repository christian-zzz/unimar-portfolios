<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\LighthouseController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AnalyticsController;
use App\Http\Controllers\API\MediaController;
use App\Http\Controllers\API\PortfolioController;
use App\Http\Controllers\API\PortfolioRevisionController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AppSettingController;
use App\Http\Controllers\API\BackupController;
use App\Http\Controllers\API\ReportController;
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

// Public API Routes (unprotected)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/public/portfolios', [PortfolioController::class, 'indexPublic']);
Route::get('/public/portfolios/{slug}', [PortfolioController::class, 'showPublic']);
Route::get('/public/categories', [CategoryController::class, 'indexPublic']);

// Protected API Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });

    // Portfolio Management Routes
    Route::get('/portfolio', [PortfolioController::class, 'getCurrent']);
    Route::put('/portfolio/save', [PortfolioController::class, 'saveDraft']);
    Route::put('/portfolio/meta', [PortfolioController::class, 'updateMeta']);
    Route::post('/portfolio/publish', [PortfolioController::class, 'publish']);
    Route::post('/portfolio/unpublish', [PortfolioController::class, 'unpublish']);

    // Portfolio Revision Routes
    Route::get('/portfolio/revisions', [PortfolioRevisionController::class, 'index']);
    Route::post('/portfolio/revisions', [PortfolioRevisionController::class, 'store']);
    Route::put('/portfolio/revisions/{id}', [PortfolioRevisionController::class, 'update']);
    Route::post('/portfolio/revisions/{id}/restore', [PortfolioRevisionController::class, 'restore']);
    Route::delete('/portfolio/revisions/{id}', [PortfolioRevisionController::class, 'destroy']);

    // User Profile Routes
    Route::put('/user/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);
    Route::post('/user/avatar', [ProfileController::class, 'updateAvatar']);
    Route::delete('/user/avatar', [ProfileController::class, 'deleteAvatar']);

    // Admin Platform Settings
    Route::get('/admin/settings', [AppSettingController::class, 'index']);
    Route::put('/admin/settings/{key}', [AppSettingController::class, 'update']);

    // Admin Stats Route
    Route::get('/admin/stats', [AdminController::class, 'stats']);

    // Admin Backup Routes
    Route::get('/admin/backups', [BackupController::class, 'index']);
    Route::post('/admin/backups', [BackupController::class, 'store']);
    Route::get('/admin/backups/{filename}/download', [BackupController::class, 'download']);
    Route::delete('/admin/backups/{filename}', [BackupController::class, 'destroy']);

    // Admin Report Routes
    Route::post('/admin/reports/generate', [ReportController::class, 'generate']);

    // Admin Student Management Actions
    Route::post('/admin/students/{id}/unpublish', [AdminController::class, 'unpublishStudentPortfolio']);
    Route::get('/admin/students/{id}/media', [AdminController::class, 'getStudentMedia']);
    Route::post('/admin/students/{id}/reset-password', [AdminController::class, 'resetStudentPassword']);
    Route::post('/admin/students/{id}/send-email', [AdminController::class, 'sendEmail']);
    Route::delete('/admin/students/{id}', [AdminController::class, 'destroyStudent']);

    // Students Management Routes
    Route::get('/students', [UserController::class, 'index']);
    Route::post('/students', [AuthController::class, 'registerStudent']);

    // Lighthouse Audit Routes
    Route::post('/audit/run', [LighthouseController::class, 'runAudit']);
    Route::post('/audit/save', [LighthouseController::class, 'saveResults']);

    // Analytics GA4 Routes (Student — filtered by own portfolio)
    Route::get('/analytics/report', [AnalyticsController::class, 'getReport']);

    // Analytics GA4 Routes (Admin — unfiltered global)
    Route::get('/admin/analytics/report', [AnalyticsController::class, 'getGlobalReport']);

    // Analytics GA4 Routes (Admin — scoped to a specific student)
    Route::get('/admin/students/{id}/analytics', [AnalyticsController::class, 'getStudentReport']);

    // Media Upload Route
    Route::get('/media', [MediaController::class, 'index']);
    Route::post('/media/upload', [MediaController::class, 'upload']);
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);

    // Category Management (Admin)
    Route::apiResource('categories', CategoryController::class);

    Route::post('/logout', [AuthController::class, 'logout']);
});
