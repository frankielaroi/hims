<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\PatientMergeController;
use App\Http\Controllers\Admin\ServicePriceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\TriageController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::middleware(['auth'])->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('password/change', [PasswordChangeController::class, 'showChangeForm'])->name('password.change');
    Route::post('password/change', [PasswordChangeController::class, 'update'])->name('password.update');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Patient Registry
    Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('patients/create', [PatientController::class, 'create'])->name('patients.create');
    Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
    Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::get('patients/{patient}/edit', [PatientController::class, 'edit'])->name('patients.edit');
    Route::patch('patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
    Route::patch('patients/{patient}/clinical', [PatientController::class, 'updateClinical'])->name('patients.clinical.update');

    // Attendance / Visits
    Route::get('patients/{patient}/visits/create', [VisitController::class, 'create'])->name('visits.create');
    Route::post('patients/{patient}/visits', [VisitController::class, 'store'])->name('visits.store');

    // Triage
    Route::get('triage', [TriageController::class, 'index'])->name('triage.index');
    Route::get('triage/{visit}', [TriageController::class, 'create'])->name('triage.create');
    Route::post('triage/{visit}', [TriageController::class, 'store'])->name('triage.store');

    // Consultation
    Route::get('consultations', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::get('consultations/{visit}', [ConsultationController::class, 'create'])->name('consultations.create');
    Route::post('consultations/{visit}', [ConsultationController::class, 'store'])->name('consultations.store');
    Route::post('consultations/{visit}/labs', [ConsultationController::class, 'orderLabs'])->name('consultations.labs.store');

    // Laboratory
    Route::get('lab', [LabController::class, 'index'])->name('lab.index');
    Route::get('lab/{labOrder}', [LabController::class, 'show'])->name('lab.show');
    Route::post('lab/{labOrder}/items/{item}/collect', [LabController::class, 'collectSpecimen'])->name('lab.items.collect');
    Route::post('lab/{labOrder}/items/{item}/result', [LabController::class, 'saveResult'])->name('lab.items.result');
    Route::post('lab/{labOrder}/verify', [LabController::class, 'verify'])->name('lab.verify');

    // Pharmacy
    Route::get('pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
    Route::get('pharmacy/{prescription}', [PharmacyController::class, 'show'])->name('pharmacy.show');
    Route::post('pharmacy/{prescription}/items/{item}/dispense', [PharmacyController::class, 'dispenseItem'])->name('pharmacy.items.dispense');

    // Admin Routes
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Price Settings
        Route::get('services', [ServicePriceController::class, 'index'])->name('services.index');
        Route::patch('services/{service}', [ServicePriceController::class, 'update'])->name('services.update');

        // Staff Management
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');

        // Patient Management
        Route::get('patients/merge', [PatientMergeController::class, 'show'])->name('patients.merge');
        Route::post('patients/merge', [PatientMergeController::class, 'merge'])->name('patients.merge.store');
    });
});
