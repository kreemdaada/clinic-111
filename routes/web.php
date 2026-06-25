<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DailyReportEditorController;
use App\Http\Controllers\Web\DoctorAdminController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\LogController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('imports.index');
    }

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

    Route::delete('/imports/{dailyReport}', [ImportController::class, 'destroy'])
        ->middleware('role:admin,accountant')
        ->name('imports.destroy');

    Route::get('/imports/{dailyReport}/income', [ImportController::class, 'downloadIncome'])
        ->middleware('role:admin,accountant,viewer')
        ->name('imports.income');

    Route::middleware('role:admin,accountant')->prefix('daily-report')->name('daily-report.')->group(function () {
        Route::get('/', [DailyReportEditorController::class, 'index'])->name('index');
        Route::post('/', [DailyReportEditorController::class, 'store'])->name('store');
        Route::delete('/{dailyReport}', [DailyReportEditorController::class, 'destroy'])->name('destroy');
        Route::get('/{dailyReport}', [DailyReportEditorController::class, 'edit'])->name('edit');
        Route::get('/{dailyReport}/rows', [DailyReportEditorController::class, 'rows'])->name('rows');
        Route::post('/{dailyReport}/rows', [DailyReportEditorController::class, 'saveRow'])->name('rows.save');
        Route::delete('/{dailyReport}/rows/{dailyWorkRow}', [DailyReportEditorController::class, 'deleteRow'])->name('rows.delete');
        Route::post('/{dailyReport}/preview', [DailyReportEditorController::class, 'preview'])->name('preview');
    });

    Route::post('/doctors', [DailyReportEditorController::class, 'storeDoctor'])
        ->middleware('role:admin')
        ->name('doctors.store');

    Route::middleware('role:admin')->prefix('doctors')->name('doctors.')->group(function () {
        Route::get('/', [DoctorAdminController::class, 'index'])->name('index');
        Route::put('/{doctor}', [DoctorAdminController::class, 'update'])->name('update');
        Route::delete('/{doctor}', [DoctorAdminController::class, 'destroy'])->name('destroy');
    });

    Route::get('/doctors/{doctor}/treatments', [DailyReportEditorController::class, 'doctorTreatments'])
        ->middleware('role:admin,accountant')
        ->name('doctors.treatments');

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
