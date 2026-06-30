<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicAdminController;
use App\Http\Controllers\Api\ClinicOnboardingController;
use App\Http\Controllers\Api\ConfigurationStatusController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\DoctorFixedFeeAdminController;
use App\Http\Controllers\Api\LabAdminController;
use App\Http\Controllers\Api\LabPriceAdminController;
use App\Http\Controllers\Api\MonthlyIncomeController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\TreatmentAdminController;
use App\Http\Controllers\Api\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/register-clinic', [ClinicOnboardingController::class, 'store'])
    ->middleware('throttle:register-clinic');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/configuration/status', [ConfigurationStatusController::class, 'show']);
    });

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

    Route::middleware('role:admin')->prefix('admin/clinics')->group(function () {
        Route::get('/', [ClinicAdminController::class, 'index']);
        Route::post('/', [ClinicAdminController::class, 'store']);
        Route::put('/{clinic}', [ClinicAdminController::class, 'update']);
        Route::delete('/{clinic}', [ClinicAdminController::class, 'destroy']);
        Route::post('/{clinic}/activate', [ClinicAdminController::class, 'activate']);
    });

    Route::middleware('role:admin')->prefix('admin/labs')->group(function () {
        Route::get('/', [LabAdminController::class, 'index']);
        Route::post('/', [LabAdminController::class, 'store']);
        Route::put('/{lab}', [LabAdminController::class, 'update']);
        Route::delete('/{lab}', [LabAdminController::class, 'destroy']);
        Route::post('/{lab}/activate', [LabAdminController::class, 'activate']);
    });

    Route::middleware('role:admin')->prefix('admin/lab-prices')->group(function () {
        Route::get('/', [LabPriceAdminController::class, 'index']);
        Route::post('/', [LabPriceAdminController::class, 'store']);
        Route::put('/{labPrice}', [LabPriceAdminController::class, 'update']);
        Route::delete('/{labPrice}', [LabPriceAdminController::class, 'destroy']);
        Route::post('/{labPrice}/activate', [LabPriceAdminController::class, 'activate']);
        Route::post('/{labPrice}/duplicate', [LabPriceAdminController::class, 'duplicate']);
    });

    Route::middleware('role:admin')->prefix('admin/doctor-fixed-fees')->group(function () {
        Route::get('/', [DoctorFixedFeeAdminController::class, 'index']);
        Route::post('/', [DoctorFixedFeeAdminController::class, 'store']);
        Route::put('/{doctorFixedFee}', [DoctorFixedFeeAdminController::class, 'update']);
        Route::delete('/{doctorFixedFee}', [DoctorFixedFeeAdminController::class, 'destroy']);
        Route::post('/{doctorFixedFee}/activate', [DoctorFixedFeeAdminController::class, 'activate']);
        Route::post('/{doctorFixedFee}/duplicate', [DoctorFixedFeeAdminController::class, 'duplicate']);
    });

    Route::middleware('role:admin')->prefix('admin/treatments')->group(function () {
        Route::get('/', [TreatmentAdminController::class, 'index']);
        Route::post('/', [TreatmentAdminController::class, 'store']);
        Route::put('/{treatment}', [TreatmentAdminController::class, 'update']);
        Route::delete('/{treatment}', [TreatmentAdminController::class, 'destroy']);
        Route::post('/{treatment}/activate', [TreatmentAdminController::class, 'activate']);
    });

    Route::middleware('role:admin')->prefix('users')->group(function () {
        Route::get('/', [UserAdminController::class, 'index']);
        Route::post('/', [UserAdminController::class, 'store']);
        Route::put('/{managedUser}', [UserAdminController::class, 'update']);
        Route::delete('/{managedUser}', [UserAdminController::class, 'destroy']);
        Route::post('/{managedUser}/reset-password', [UserAdminController::class, 'resetPassword']);
    });
});
