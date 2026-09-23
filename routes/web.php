<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\PatientMergeController;
use App\Http\Controllers\Admin\ServicePriceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AncController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClinicalTaskController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\EmergencyController;
use App\Http\Controllers\ImciController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\LabInventoryController;
use App\Http\Controllers\MaternalDeathController;
use App\Http\Controllers\NhisController;
use App\Http\Controllers\PaediatricController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\PostnatalController;
use App\Http\Controllers\RadiologyController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\ResuscitationController;
use App\Http\Controllers\TheatreController;
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

    // Emergency
    Route::get('emergency', [EmergencyController::class, 'board'])->name('emergency.board');
    Route::get('emergency/register', [EmergencyController::class, 'create'])->name('emergency.create');
    Route::post('emergency/register', [EmergencyController::class, 'store'])->name('emergency.store');
    Route::post('visits/{visit}/resuscitation', [ResuscitationController::class, 'store'])->name('resuscitation.store');
    Route::post('resuscitation/{resuscitation}/complete', [ResuscitationController::class, 'complete'])->name('resuscitation.complete');

    // Triage
    Route::get('triage', [TriageController::class, 'index'])->name('triage.index');
    Route::get('triage/{visit}', [TriageController::class, 'create'])->name('triage.create');
    Route::post('triage/{visit}', [TriageController::class, 'store'])->name('triage.store');

    // Consultation
    Route::get('consultations', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::get('consultations/{visit}', [ConsultationController::class, 'create'])->name('consultations.create');
    Route::post('consultations/{visit}', [ConsultationController::class, 'store'])->name('consultations.store');
    Route::post('consultations/{consultation}/heartbeat', [ConsultationController::class, 'heartbeat'])->name('consultations.heartbeat');
    Route::post('consultations/{consultation}/autosave', [ConsultationController::class, 'autosave'])->name('consultations.autosave');
    Route::post('consultations/{consultation}/break-glass', [ConsultationController::class, 'breakGlass'])->name('consultations.break-glass');
    Route::post('consultations/{consultation}/amend', [ConsultationController::class, 'amend'])->name('consultations.amend');
    Route::post('consultations/{visit}/labs', [ConsultationController::class, 'orderLabs'])->name('consultations.labs.store');

    // Laboratory
    // The finer boundaries inside the laboratory — who may collect, who may
    // enter a metric and who may authorise a release — are enforced by
    // LabOrderPolicy, LabOrderItemPolicy and LabSamplePolicy rather than here.
    Route::get('lab', [LabController::class, 'index'])->middleware('can_do:view_clinical_history')->name('lab.index');
    Route::get('lab/create', [LabController::class, 'create'])->middleware('can_do:order_lab_tests')->name('lab.create');
    Route::post('lab', [LabController::class, 'store'])->middleware('can_do:order_lab_tests')->name('lab.store');
    Route::get('lab/{labOrder}', [LabController::class, 'show'])->name('lab.show');
    Route::post('lab/{labOrder}/collect', [LabController::class, 'collectSpecimen'])->name('lab.collect');
    Route::post('lab/samples/{sample}/receive', [LabController::class, 'receiveSample'])->name('lab.samples.receive');
    Route::post('lab/samples/{sample}/reject', [LabController::class, 'rejectSample'])->name('lab.samples.reject');
    Route::post('lab/{labOrder}/items/{item}/result', [LabController::class, 'saveResult'])->name('lab.items.result');
    Route::post('lab/{labOrder}/authorize', [LabController::class, 'authorizeResults'])->name('lab.authorize');
    Route::post('lab/{labOrder}/verify', [LabController::class, 'verify'])->name('lab.verify');
    Route::post('lab/{labOrder}/review', [LabController::class, 'review'])->name('lab.review');
    Route::post('lab/{labOrder}/items/{item}/acknowledge-critical', [LabController::class, 'acknowledgeCritical'])->name('lab.items.acknowledge-critical');

    // Lab Inventory
    Route::middleware('can_do:manage_lab_inventory')->group(function () {
        Route::get('lab-inventory', [LabInventoryController::class, 'index'])->name('lab.inventory.index');
        Route::get('lab-inventory/create', [LabInventoryController::class, 'create'])->name('lab.inventory.create');
        Route::post('lab-inventory', [LabInventoryController::class, 'store'])->name('lab.inventory.store');
    });

    // Paediatrics: growth and immunisation
    Route::get('patients/{patient}/paediatrics', [PaediatricController::class, 'show'])->name('paediatrics.show');
    Route::post('patients/{patient}/growth', [PaediatricController::class, 'storeMeasurement'])->name('paediatrics.growth.store');
    Route::post('patients/{patient}/immunizations', [PaediatricController::class, 'storeImmunization'])->name('paediatrics.immunizations.store');

    // IMCI: integrated management of childhood illness
    Route::get('patients/{patient}/imci', [ImciController::class, 'show'])->name('imci.show');
    Route::post('visits/{visit}/imci', [ImciController::class, 'store'])->name('imci.store');

    // Antenatal care
    Route::get('patients/{patient}/anc', [AncController::class, 'show'])->name('anc.show');
    Route::post('visits/{visit}/anc', [AncController::class, 'store'])->name('anc.store');

    // Labour, delivery and the newborn
    Route::middleware('can_do:manage_deliveries')->group(function () {
        Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::post('visits/{visit}/deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
        Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
        Route::post('deliveries/{delivery}/observations', [DeliveryController::class, 'storeObservation'])->name('deliveries.observations.store');
        Route::post('deliveries/{delivery}/outcome', [DeliveryController::class, 'storeOutcome'])->name('deliveries.outcome.store');
        Route::post('deliveries/{delivery}/newborns/{newborn}/register', [DeliveryController::class, 'registerNewborn'])->name('deliveries.newborns.register');

        // Postnatal care
        Route::get('postnatal', [PostnatalController::class, 'index'])->name('postnatal.index');
        Route::post('deliveries/{delivery}/postnatal', [PostnatalController::class, 'store'])->name('postnatal.store');
    });

    // Theatre
    Route::middleware('can_do:manage_theatre')->group(function () {
        Route::get('theatre', [TheatreController::class, 'index'])->name('theatre.index');
        Route::post('visits/{visit}/operations', [TheatreController::class, 'store'])->name('theatre.store');
        Route::post('operations/{operation}/complete', [TheatreController::class, 'complete'])->name('theatre.complete');
    });

    // Maternal death surveillance and response
    Route::middleware('can_do:manage_maternal_death_surveillance')->group(function () {
        Route::get('maternal-deaths', [MaternalDeathController::class, 'index'])->name('maternal-deaths.index');
        Route::post('maternal-deaths/{deathRecord}/classify', [MaternalDeathController::class, 'classify'])->name('maternal-deaths.classify');
        Route::post('maternal-deaths/{deathRecord}/notify', [MaternalDeathController::class, 'notify'])->name('maternal-deaths.notify');
        Route::post('maternal-deaths/{deathRecord}/review', [MaternalDeathController::class, 'review'])->name('maternal-deaths.review');
    });

    // Clinical tasks
    Route::get('tasks', [ClinicalTaskController::class, 'index'])->middleware('can_do:manage_clinical_tasks')->name('tasks.index');
    Route::post('tasks/{task}/complete', [ClinicalTaskController::class, 'complete'])->name('tasks.complete');
    Route::post('tasks/{task}/reassign', [ClinicalTaskController::class, 'reassign'])->name('tasks.reassign');

    // Clinical timeline
    Route::get('patients/{patient}/timeline', [PatientController::class, 'timeline'])->name('patients.timeline');

    // Radiology
    Route::middleware('can_do:view_clinical_history')->group(function () {
        Route::get('radiology', [RadiologyController::class, 'index'])->name('radiology.index');
        Route::get('radiology/{radiologyOrder}', [RadiologyController::class, 'show'])->name('radiology.show');
        Route::get('radiology/{radiologyOrder}/attachments/{attachment}', [RadiologyController::class, 'downloadAttachment'])->name('radiology.attachments.download');
    });
    Route::post('consultations/{visit}/radiology', [RadiologyController::class, 'store'])->middleware('can_do:order_imaging')->name('radiology.store');
    Route::post('radiology/{radiologyOrder}/review', [RadiologyController::class, 'review'])->middleware('can_do:order_imaging')->name('radiology.review');
    Route::middleware('can_do:process_radiology_orders')->group(function () {
        Route::post('radiology/{radiologyOrder}/performed', [RadiologyController::class, 'markPerformed'])->name('radiology.performed');
        Route::post('radiology/{radiologyOrder}/report', [RadiologyController::class, 'saveReport'])->name('radiology.report');
        Route::post('radiology/{radiologyOrder}/cancel', [RadiologyController::class, 'cancel'])->name('radiology.cancel');
        Route::post('radiology/{radiologyOrder}/attachments', [RadiologyController::class, 'storeAttachment'])->name('radiology.attachments.store');
        Route::delete('radiology/{radiologyOrder}/attachments/{attachment}', [RadiologyController::class, 'destroyAttachment'])->name('radiology.attachments.destroy');
    });

    // Pharmacy
    Route::middleware('can_do:dispense_medication')->group(function () {
        Route::get('pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
        Route::get('pharmacy/{prescription}', [PharmacyController::class, 'show'])->name('pharmacy.show');
        Route::post('pharmacy/{prescription}/items/{item}/dispense', [PharmacyController::class, 'dispenseItem'])->name('pharmacy.items.dispense');
    });

    // Billing
    Route::middleware('can_do:manage_billing')->group(function () {
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/{invoice}', [BillingController::class, 'show'])->name('billing.show');
        Route::post('billing/{invoice}/payments', [BillingController::class, 'recordPayment'])->name('billing.payments.store');
        Route::post('billing/payments/{payment}/settle', [BillingController::class, 'settlePayment'])->name('billing.payments.settle');
    });

    // NHIS Claims
    Route::middleware('can_do:manage_nhis_claims')->group(function () {
        Route::get('nhis', [NhisController::class, 'index'])->name('nhis.index');
        Route::post('nhis/invoices/{invoice}/claim', [NhisController::class, 'generateClaim'])->name('nhis.claims.generate');
        Route::get('nhis/{claim}', [NhisController::class, 'show'])->name('nhis.show');
        Route::post('nhis/{claim}/submit', [NhisController::class, 'submit'])->name('nhis.submit');
        Route::post('nhis/{claim}/vet', [NhisController::class, 'markVetted'])->name('nhis.vet');
        Route::post('nhis/{claim}/pay', [NhisController::class, 'markPaid'])->name('nhis.pay');
        Route::post('nhis/{claim}/reject', [NhisController::class, 'reject'])->name('nhis.reject');
        Route::post('nhis/{claim}/resubmit', [NhisController::class, 'resubmit'])->name('nhis.resubmit');
        Route::get('nhis/{claim}/export', [NhisController::class, 'export'])->name('nhis.export');
    });

    // Admissions & Beds
    Route::middleware('can_do:manage_admissions')->group(function () {
        Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::post('admissions/visits/{visit}/assign-bed', [AdmissionController::class, 'assignBed'])->name('admissions.assign-bed');
        Route::get('admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
        Route::post('admissions/{admission}/transfer', [AdmissionController::class, 'transfer'])->name('admissions.transfer');
        Route::post('admissions/{admission}/rounds', [AdmissionController::class, 'storeRoundNote'])->name('admissions.rounds.store');
        Route::post('admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])->name('admissions.discharge');
        Route::post('beds/{bed}/mark-available', [AdmissionController::class, 'markBedAvailable'])->name('beds.mark-available');
    });

    // Referrals
    Route::middleware('can_do:manage_referrals')->group(function () {
        Route::get('referrals', [ReferralController::class, 'index'])->name('referrals.index');
        Route::post('referrals/{referral}/accept', [ReferralController::class, 'accept'])->name('referrals.accept');
        Route::post('referrals/{referral}/decline', [ReferralController::class, 'decline'])->name('referrals.decline');
        Route::post('referrals/{referral}/complete', [ReferralController::class, 'complete'])->name('referrals.complete');
        Route::post('referrals/{referral}/pre-referral-treatment', [ReferralController::class, 'recordPreReferralTreatment'])->name('referrals.pre-referral.store');
        Route::get('referrals/{referral}/print', [ReferralController::class, 'print'])->name('referrals.print');
    });

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
