<?php

use App\Http\Controllers\Web\ClinicAdminController;
use App\Http\Controllers\Web\ClinicOnboardingController;
use App\Http\Controllers\Web\ConfigurationDashboardController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DailyReportEditorController;
use App\Http\Controllers\Web\DoctorFixedFeeAdminController;
use App\Http\Controllers\Web\DoctorAdminController;
use App\Http\Controllers\Web\ImportController;
use App\Http\Controllers\Web\LabAdminController;
use App\Http\Controllers\Web\LabPriceAdminController;
use App\Http\Controllers\Web\LogController;
use App\Http\Controllers\Web\ReportLockController;
use App\Http\Controllers\Web\TreatmentAdminController;
use App\Http\Controllers\Web\UserAdminController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('imports.index');
    }

    return redirect()->route('login');
});

Route::get('/register-clinic', [ClinicOnboardingController::class, 'create'])->name('register-clinic.create');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/register-clinic', [ClinicOnboardingController::class, 'store'])->name('register-clinic.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/imports', [ImportController::class, 'index'])
        ->middleware('role:admin,accountant,viewer')
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

    Route::middleware('role:admin,accountant,viewer')->prefix('daily-report')->name('daily-report.')->group(function () {
        Route::get('/', [DailyReportEditorController::class, 'index'])->name('index');
        Route::get('/{dailyReport}', [DailyReportEditorController::class, 'edit'])->name('edit');
        Route::get('/{dailyReport}/rows', [DailyReportEditorController::class, 'rows'])->name('rows');
    });

    Route::middleware('role:admin,accountant')->prefix('daily-report')->name('daily-report.')->group(function () {
        Route::post('/', [DailyReportEditorController::class, 'store'])->name('store');
        Route::delete('/{dailyReport}', [DailyReportEditorController::class, 'destroy'])->name('destroy');
        Route::post('/{dailyReport}/rows', [DailyReportEditorController::class, 'saveRow'])->name('rows.save');
        Route::delete('/{dailyReport}/rows/{dailyWorkRow}', [DailyReportEditorController::class, 'deleteRow'])->name('rows.delete');
        Route::post('/{dailyReport}/preview', [DailyReportEditorController::class, 'preview'])->name('preview');
    });

    Route::middleware('role:admin')->prefix('daily-report')->name('daily-report.')->group(function () {
        Route::post('/doctors', [DailyReportEditorController::class, 'storeDoctor'])->name('doctors.store');
        Route::post('/{dailyReport}/approve', [ReportLockController::class, 'approve'])->name('approve');
        Route::post('/{dailyReport}/unlock', [ReportLockController::class, 'unlock'])->name('unlock');
    });

    Route::middleware('role:admin')->prefix('doctors')->name('doctors.')->group(function () {
        Route::get('/', [DoctorAdminController::class, 'index'])->name('index');
        Route::post('/', [DoctorAdminController::class, 'store'])->name('store');
        Route::put('/{doctor}', [DoctorAdminController::class, 'update'])->name('update');
        Route::delete('/{doctor}', [DoctorAdminController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('role:admin')->prefix('configuration')->name('configuration.')->group(function () {
        Route::get('/', [ConfigurationDashboardController::class, 'index'])->name('dashboard');
    });

    Route::middleware('role:admin')->prefix('clinics')->name('clinics.')->group(function () {
        Route::get('/', [ClinicAdminController::class, 'index'])->name('index');
        Route::post('/', [ClinicAdminController::class, 'store'])->name('store');
        Route::put('/{clinic}', [ClinicAdminController::class, 'update'])->name('update');
        Route::delete('/{clinic}', [ClinicAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{clinic}/activate', [ClinicAdminController::class, 'activate'])->name('activate');
    });

    Route::middleware('role:admin')->prefix('labs')->name('labs.')->group(function () {
        Route::get('/', [LabAdminController::class, 'index'])->name('index');
        Route::post('/', [LabAdminController::class, 'store'])->name('store');
        Route::put('/{lab}', [LabAdminController::class, 'update'])->name('update');
        Route::delete('/{lab}', [LabAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{lab}/activate', [LabAdminController::class, 'activate'])->name('activate');
    });

    Route::middleware('role:admin')->prefix('lab-prices')->name('lab-prices.')->group(function () {
        Route::get('/', [LabPriceAdminController::class, 'index'])->name('index');
        Route::post('/', [LabPriceAdminController::class, 'store'])->name('store');
        Route::put('/{labPrice}', [LabPriceAdminController::class, 'update'])->name('update');
        Route::delete('/{labPrice}', [LabPriceAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{labPrice}/activate', [LabPriceAdminController::class, 'activate'])->name('activate');
        Route::post('/{labPrice}/duplicate', [LabPriceAdminController::class, 'duplicate'])->name('duplicate');
    });

    Route::middleware('role:admin')->prefix('doctor-fixed-fees')->name('doctor-fixed-fees.')->group(function () {
        Route::get('/', [DoctorFixedFeeAdminController::class, 'index'])->name('index');
        Route::post('/', [DoctorFixedFeeAdminController::class, 'store'])->name('store');
        Route::put('/{doctorFixedFee}', [DoctorFixedFeeAdminController::class, 'update'])->name('update');
        Route::delete('/{doctorFixedFee}', [DoctorFixedFeeAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{doctorFixedFee}/activate', [DoctorFixedFeeAdminController::class, 'activate'])->name('activate');
        Route::post('/{doctorFixedFee}/duplicate', [DoctorFixedFeeAdminController::class, 'duplicate'])->name('duplicate');
    });

    Route::middleware('role:admin')->prefix('treatments')->name('treatments.')->group(function () {
        Route::get('/', [TreatmentAdminController::class, 'index'])->name('index');
        Route::post('/', [TreatmentAdminController::class, 'store'])->name('store');
        Route::put('/{treatment}', [TreatmentAdminController::class, 'update'])->name('update');
        Route::delete('/{treatment}', [TreatmentAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{treatment}/activate', [TreatmentAdminController::class, 'activate'])->name('activate');
    });

    Route::middleware('role:admin')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserAdminController::class, 'index'])->name('index');
        Route::post('/', [UserAdminController::class, 'store'])->name('store');
        Route::put('/{user}', [UserAdminController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{user}/reset-password', [UserAdminController::class, 'resetPassword'])->name('reset-password');
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
