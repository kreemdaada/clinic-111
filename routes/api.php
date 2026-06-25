<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\LabPriceAdminController;
use App\Http\Controllers\Api\MonthlyIncomeController;
use App\Http\Controllers\Api\UserAdminController;
use App\Http\Controllers\Api\ReferenceDataController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('role:admin,accountant,viewer')->group(function () {
        Route::get('/doctors', [ReferenceDataController::class, 'doctors']);
        Route::get('/treatments', [ReferenceDataController::class, 'treatments']);
        Route::get('/labs', [ReferenceDataController::class, 'labs']);

        Route::get('/monthly-income', [MonthlyIncomeController::class, 'index']);

        Route::get('/daily-reports/{dailyReport}', [DailyReportController::class, 'show']);
        Route::get('/daily-reports/{dailyReport}/validation-summary', [DailyReportController::class, 'validationSummary']);
    });

    Route::post('/daily-reports/import', [DailyReportController::class, 'import'])
        ->middleware(['role:admin,accountant', 'throttle:20,1']);

    Route::middleware('role:admin')->prefix('lab-prices')->group(function () {
        Route::post('/', [LabPriceAdminController::class, 'store']);
        Route::put('/{labPrice}', [LabPriceAdminController::class, 'update']);
        Route::delete('/{labPrice}', [LabPriceAdminController::class, 'destroy']);
    });

    Route::middleware('role:admin')->prefix('users')->group(function () {
        Route::get('/', [UserAdminController::class, 'index']);
        Route::post('/', [UserAdminController::class, 'store']);
        Route::put('/{user}', [UserAdminController::class, 'update']);
        Route::delete('/{user}', [UserAdminController::class, 'destroy']);
        Route::post('/{user}/reset-password', [UserAdminController::class, 'resetPassword']);
    });
});
