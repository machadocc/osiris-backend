<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\RecurringTransactionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavingsGoalController;
use App\Http\Controllers\Api\SpendingLimitController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Middleware\GenerateDueRecurringTransactions;
use App\Http\Middleware\SendDueWeeklySummaries;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', GenerateDueRecurringTransactions::class, SendDueWeeklySummaries::class])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateProfile']);
    Route::put('/auth/me/password', [AuthController::class, 'changePassword']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/compare', [DashboardController::class, 'compare']);

    Route::apiResource('categories', CategoryController::class)->except(['show']);
    Route::apiResource('accounts', AccountController::class)->except(['show']);
    Route::apiResource('transactions', TransactionController::class)->except(['show']);
    Route::apiResource('spending-limits', SpendingLimitController::class)->except(['show']);
    Route::apiResource('savings-goals', SavingsGoalController::class)->except(['show']);
    Route::apiResource('recurring-transactions', RecurringTransactionController::class)->except(['show']);

    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
});
