<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ── Public Routes ─────────────────────────────────────────────────────────
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Google OAuth
Route::get('/auth/google',          [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// Products & Categories (public browsing with geo/filter support)
Route::get('/products',           [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories',         [CategoryController::class, 'index']);

// Email Verification (signed URL — public)
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// ── Protected Routes (require Sanctum token) ──────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth & Profile ──────────────────────────────────────────────────
    Route::get('/me',            [AuthController::class, 'me']);
    Route::post('/logout',       [AuthController::class, 'logout']);
    Route::post('/upgrade-role', [AuthController::class, 'upgradeRole']);

    // Phase 2.2 — Profile, Password, Location
    Route::put('/profile',  [AuthController::class, 'updateProfile']);
    Route::put('/password', [AuthController::class, 'updatePassword']);
    Route::put('/location', [AuthController::class, 'updateLocation']);

    // Email Resend
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // ── Products (Seller) ───────────────────────────────────────────────
    Route::get('/my-products',                  [ProductController::class, 'myProducts']);
    Route::post('/products',                    [ProductController::class, 'store']);
    Route::put('/products/{product}',           [ProductController::class, 'update']);
    Route::delete('/products/{product}',        [ProductController::class, 'destroy']);

    // Phase 2.3 — Product status toggle & multiple images
    Route::patch('/products/{product}/status',  [ProductController::class, 'toggleStatus']);
    Route::post('/products/{product}/images',   [ProductImageController::class, 'store']);
    Route::delete('/products/{product}/images/{image}', [ProductImageController::class, 'destroy']);
    Route::patch('/products/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary']);

    // ── Chat & Notifications ────────────────────────────────────────────
    Route::get('/conversations',                        [ChatController::class, 'conversations']);
    Route::get('/notifications/unread-count',           [ChatController::class, 'unreadCount']);
    Route::get('/products/{product}/chats/{user}',      [ChatController::class, 'messages']);
    Route::post('/products/{product}/chats',            [ChatController::class, 'store']);
    Route::patch('/products/{product}/chats/{user}/read', [ChatController::class, 'markAsRead']);

    // ── Reports ─────────────────────────────────────────────────────────
    Route::get('/reports',          [ReportController::class, 'index']);
    Route::post('/reports',         [ReportController::class, 'store']);
    Route::get('/reports/{report}', [ReportController::class, 'show']);
    Route::put('/reports/{report}', [ReportController::class, 'update']);

    // ── Admin Dashboard ──────────────────────────────────────────────────
    Route::get('/admin/stats',             [AdminDashboardController::class, 'stats']);
    Route::get('/admin/recent-activities', [AdminDashboardController::class, 'recentActivities']);

    // ── User Management (Admin) ──────────────────────────────────────────
    Route::get('/users',                    [UserController::class, 'index']);
    Route::get('/users/{user}',             [UserController::class, 'show']);
    Route::patch('/users/{user}/status',    [UserController::class, 'toggleStatus']); // Phase 2.4
    Route::delete('/users/{user}',          [UserController::class, 'destroy']);
});
