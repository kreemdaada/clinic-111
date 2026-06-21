<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\LogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/imports', [ImportController::class, 'index'])
        ->middleware('role:admin,accountant')
        ->name('imports.index');

    Route::post('/imports', [ImportController::class, 'store'])
        ->middleware('role:admin,accountant')
        ->name('imports.store');

    Route::get('/imports/{dailyReport}/income', [ImportController::class, 'downloadIncome'])
        ->middleware('role:admin,accountant,viewer')
        ->name('imports.income');

    Route::get('/docs/treatment-rules', function () {
        return view('docs.treatment-rules');
    })
        ->middleware('role:admin,accountant,viewer')
        ->name('docs.treatment-rules');

    Route::get('/logs', [LogController::class, 'index'])
        ->middleware('role:admin,accountant')
        ->name('logs.index');

    Route::get('/logs/extraction/{dailyReport}', [LogController::class, 'extraction'])
        ->middleware('role:admin,accountant')
        ->name('logs.extraction');

    Route::get('/logs/extraction/{dailyReport}/download', [LogController::class, 'downloadExtraction'])
        ->middleware('role:admin,accountant')
        ->name('logs.extraction.download');
});
