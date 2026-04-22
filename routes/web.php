<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [PortalController::class, 'login'])->name('login');
    Route::post('/acceder', [PortalController::class, 'authenticate'])->name('authenticate');
    Route::get('/crear-cuenta', [PortalController::class, 'register'])->name('register');
    Route::post('/crear-cuenta', [PortalController::class, 'storeRegister'])->name('register.store');
    Route::get('/recuperar-contrasena', [PortalController::class, 'forgotPassword'])->name('password.request');
    Route::post('/recuperar-contrasena', [PortalController::class, 'sendRecoveryMessage'])->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [PortalController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/restablecer-contrasena', [PortalController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/salir', [PortalController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/unidades', [PortalController::class, 'units'])->name('units');
    Route::post('/unidades', [PortalController::class, 'storeUnit'])->name('units.store');
    Route::patch('/unidades/{unit}', [PortalController::class, 'updateUnit'])->name('units.update');
    Route::patch('/unidades/{unit}/estatus', [PortalController::class, 'updateUnitStatus'])->name('units.status');
    Route::patch('/unidades/cuota/modelo', [PortalController::class, 'updateFeeType'])->name('units.fee-type');
    Route::delete('/unidades/{unit}', [PortalController::class, 'destroyUnit'])->name('units.destroy');
    Route::get('/amenidades', [PortalController::class, 'amenities'])->name('amenities');
    Route::post('/amenidades', [PortalController::class, 'storeAmenity'])->name('amenities.store');
    Route::delete('/amenidades/{amenity}', [PortalController::class, 'destroyAmenity'])->name('amenities.destroy');
    Route::post('/amenidades/reservas', [PortalController::class, 'storeAmenityReservation'])->name('amenities.reservations.store');
    Route::patch('/amenidades/reservas/{reservation}', [PortalController::class, 'updateAmenityReservation'])->name('amenities.reservations.update');
    Route::patch('/amenidades/reservas/{reservation}/cancelar', [PortalController::class, 'cancelAmenityReservation'])->name('amenities.reservations.cancel');
    Route::delete('/amenidades/reservas/{reservation}', [PortalController::class, 'destroyAmenityReservation'])->name('amenities.reservations.destroy');
    Route::get('/mantenimiento', [PortalController::class, 'maintenance'])->name('maintenance');
    Route::post('/mantenimiento/proveedores', [PortalController::class, 'storeProvider'])->name('providers.store');
    Route::post('/mantenimiento/tareas', [PortalController::class, 'storeMaintenanceTask'])->name('maintenance.tasks.store');
    Route::post('/mantenimiento/gastos', [PortalController::class, 'storeMaintenanceExpense'])->name('maintenance.expenses.store');
    Route::get('/mantenimiento/reporte-pdf', [PortalController::class, 'maintenancePdf'])->name('maintenance.pdf');
    Route::get('/mantenimiento/gastos/reporte-mensual-pdf', [PortalController::class, 'maintenanceMonthlyExpensesPdf'])->name('maintenance.expenses.monthly.pdf');
    Route::get('/mantenimiento/gastos/{expense}/recibo-pdf', [PortalController::class, 'maintenanceExpenseReceiptPdf'])->name('maintenance.expenses.receipt.pdf');
    Route::get('/mantenimiento/gastos/{expense}/documento', [PortalController::class, 'maintenanceExpenseDocument'])->name('maintenance.expenses.document');
    Route::get('/cobranza', [PortalController::class, 'billing'])->name('billing');
    Route::post('/cobranza/pagos', [PortalController::class, 'storePayment'])->name('payments.store');
    Route::get('/cobranza/estado-pdf', [PortalController::class, 'billingPdf'])->name('billing.pdf');
    Route::get('/cobranza/recibo/{payment}', [PortalController::class, 'paymentReceiptPdf'])->name('payments.receipt.pdf');
    Route::get('/cobranza/reporte-mensual-residente-pdf', [PortalController::class, 'residentMonthlyReportPdf'])->name('billing.resident.monthly.pdf');
    Route::get('/cobranza/reporte-pdf', [PortalController::class, 'billingReportPdf'])->name('billing.report.pdf');
    Route::get('/cobranza/deudores-pdf', [PortalController::class, 'debtorsReportPdf'])->name('billing.debtors.pdf');
    Route::get('/configuracion', [PortalController::class, 'settings'])->name('settings');
    Route::post('/configuracion/perfil', [PortalController::class, 'updateSettings'])->name('settings.update');
    Route::post('/configuracion/infraestructura', [PortalController::class, 'updateInfrastructure'])->name('settings.infrastructure.update');
    Route::post('/configuracion/usuarios', [PortalController::class, 'storeUser'])->name('users.store');
    Route::patch('/configuracion/usuarios/{user}', [PortalController::class, 'updateUser'])->name('users.update');
    Route::delete('/configuracion/usuarios/{user}', [PortalController::class, 'destroyUser'])->name('users.destroy');
});
