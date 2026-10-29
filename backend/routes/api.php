<?php

declare(strict_types=1);

use App\Http\Controllers\AuditEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\FeatureDefinitionController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\VendorUserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:control-login')->group(function (): void {
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/mfa/enroll/start', [AuthController::class, 'enrollStart']);
        Route::post('auth/mfa/enroll/confirm', [AuthController::class, 'enrollConfirm']);
        Route::post('auth/mfa/challenge', [AuthController::class, 'challenge']);
    });

    Route::middleware(['auth:sanctum', 'mfa'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/mfa/step-up', [AuthController::class, 'stepUp']);
        Route::post('auth/sessions/revoke-others', [AuthController::class, 'revokeOtherSessions'])
            ->middleware('permission:sessions.revoke');

        Route::get('overview', [OverviewController::class, 'show'])
            ->middleware('permission:overview.view');

        Route::get('customers', [CustomerController::class, 'index'])
            ->middleware('permission:customers.view');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])
            ->middleware('permission:customers.view');
        Route::post('customers', [CustomerController::class, 'store'])
            ->middleware(['permission:customers.create', 'mfa.fresh']);
        Route::put('customers/{customer}', [CustomerController::class, 'update'])
            ->middleware(['permission:customers.update', 'mfa.fresh']);

        Route::get('installations', [InstallationController::class, 'index'])
            ->middleware('permission:installations.view');
        Route::get('installations/{installation}', [InstallationController::class, 'show'])
            ->middleware('permission:installations.view');
        Route::post('installations', [InstallationController::class, 'store'])
            ->middleware(['permission:installations.create', 'mfa.fresh']);
        Route::put('installations/{installation}', [InstallationController::class, 'update'])
            ->middleware(['permission:installations.update', 'mfa.fresh']);
        Route::post('installations/{installation}/approve', [InstallationController::class, 'approve'])
            ->middleware(['permission:installations.update', 'mfa.fresh']);

        Route::get('features', [FeatureDefinitionController::class, 'index'])
            ->middleware('permission:licenses.view');
        Route::get('licenses', [LicenseController::class, 'index'])
            ->middleware('permission:licenses.view');
        Route::post('installations/{installation}/licenses', [LicenseController::class, 'issue'])
            ->middleware(['permission:licenses.issue', 'mfa.fresh']);
        Route::get('licenses/{licenseRevision}/download', [LicenseController::class, 'download'])
            ->middleware(['permission:licenses.view', 'mfa.fresh']);

        Route::get('users', [VendorUserController::class, 'index'])
            ->middleware('permission:users.view');
        Route::post('users', [VendorUserController::class, 'store'])
            ->middleware(['permission:users.create', 'mfa.fresh']);
        Route::put('users/{vendor_user}', [VendorUserController::class, 'update'])
            ->middleware(['permission:users.update', 'mfa.fresh']);

        Route::get('audit-events', [AuditEventController::class, 'index'])
            ->middleware('permission:audit.view');
    });
});
