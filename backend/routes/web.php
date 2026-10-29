<?php

declare(strict_types=1);

use App\Http\Controllers\ControlCenterWebController;
use App\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn () => redirect()->away((string) config('control_center.frontend_url')))->name('control.index');
Route::get('/login', static fn () => redirect()->away(rtrim((string) config('control_center.frontend_url'), '/').'/login'))->name('login');

Route::middleware('guest')->group(function (): void {
    Route::post('/login', [WebAuthController::class, 'store'])->middleware('throttle:control-login')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [WebAuthController::class, 'destroy'])->name('logout');
    Route::post('/customers', [ControlCenterWebController::class, 'storeCustomer'])->name('control.customers.store');
    Route::post('/installations', [ControlCenterWebController::class, 'storeInstallation'])->name('control.installations.store');
    Route::post('/installations/{installation}/approve', [ControlCenterWebController::class, 'approveInstallation'])->name('control.installations.approve');
    Route::get('/installations/{installation}/license', [ControlCenterWebController::class, 'licenseForm'])->name('control.licenses.create');
    Route::post('/installations/{installation}/license', [ControlCenterWebController::class, 'issueLicense'])->name('control.licenses.store');
    Route::get('/licenses/{licenseRevision}/download', [ControlCenterWebController::class, 'downloadLicense'])->name('control.licenses.download');
});
