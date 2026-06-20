<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\MonthlyIncomeController;
use App\Http\Controllers\Api\ReferenceDataController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/doctors', [ReferenceDataController::class, 'doctors']);
    Route::get('/treatments', [ReferenceDataController::class, 'treatments']);
    Route::get('/labs', [ReferenceDataController::class, 'labs']);

    Route::get('/monthly-income', [MonthlyIncomeController::class, 'index'])
        ->middleware('role:admin,accountant,viewer');

    Route::get('/daily-reports/{dailyReport}', [DailyReportController::class, 'show'])
        ->middleware('role:admin,accountant,viewer');

    Route::post('/daily-reports/import', [DailyReportController::class, 'import'])
        ->middleware(['role:admin,accountant', 'throttle:20,1']);
});
