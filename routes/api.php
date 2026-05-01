<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\TransactionController;
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
Route::get('/promotions/packages', [PromotionController::class, 'packages']);

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

    // ── Promotions (Phase 6.1) ──────────────────────────────────────────
    Route::get('/promotions/my', [PromotionController::class, 'myPromotions']);
    Route::post('/promotions', [PromotionController::class, 'store']);

    // ── Reports ─────────────────────────────────────────────────────────
    Route::get('/reports',          [ReportController::class, 'index']);
    Route::post('/reports',         [ReportController::class, 'store']);
    Route::get('/reports/{report}', [ReportController::class, 'show']);
    Route::put('/reports/{report}', [ReportController::class, 'update']);

    // ── Transactions (Phase 3 — PRD §2.1.4, §2.2.3) ────────────────────
    Route::get('/transactions',                         [TransactionController::class, 'index']);
    Route::post('/transactions',                        [TransactionController::class, 'store']);
    Route::get('/transactions/{transaction}',           [TransactionController::class, 'show']);
    Route::patch('/transactions/{transaction}/confirm', [TransactionController::class, 'confirm']);
    Route::patch('/transactions/{transaction}/payment', [TransactionController::class, 'uploadPayment']);
    Route::patch('/transactions/{transaction}/complete',[TransactionController::class, 'complete']);
    Route::delete('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel']);

    // ── Admin Dashboard & Management ─────────────────────────────────────
    Route::get('/admin/stats',             [AdminDashboardController::class, 'stats']);
    Route::get('/admin/recent-activities', [AdminDashboardController::class, 'recentActivities']);
    Route::get('/admin/promotions',        [PromotionController::class, 'adminIndex']);

    // Admin: Categories (Phase 5.2)
    Route::post('/admin/categories',       [CategoryController::class, 'store']);
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy']);

    // Admin: Products (Phase 5.1)
    Route::get('/admin/products',          [AdminDashboardController::class, 'allProducts']);
    Route::delete('/admin/products/{product}', [AdminDashboardController::class, 'removeProduct']);
    // ── User Management (Admin) ──────────────────────────────────────────
    Route::get('/users',                    [UserController::class, 'index']);
    Route::get('/users/{user}',             [UserController::class, 'show']);
    Route::patch('/users/{user}/status',    [UserController::class, 'toggleStatus']);
    Route::delete('/users/{user}',          [UserController::class, 'destroy']);
});
