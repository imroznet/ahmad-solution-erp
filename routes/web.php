<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CompanyAddressController;
use App\Http\Controllers\CompanyDocumentController;
use App\Http\Controllers\CompanyDocumentDownloadController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentCategoryController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SystemStatusController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])
        ->middleware('permission:users.create')
        ->name('users.create');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:users.update')
        ->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update')
        ->name('users.update');

    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
        ->middleware('permission:roles.manage')
        ->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.manage')
        ->name('roles.update');

    Route::get('/audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');

    Route::get('/system', SystemStatusController::class)
        ->middleware('permission:system.view')
        ->name('system.status');

    Route::get('/company', [CompanyProfileController::class, 'show'])
        ->middleware('permission:company.view')
        ->name('company.show');
    Route::get('/company/profile/edit', [CompanyProfileController::class, 'edit'])
        ->middleware('permission:company.update')
        ->name('company.edit');
    Route::put('/company/profile', [CompanyProfileController::class, 'update'])
        ->middleware('permission:company.update')
        ->name('company.update');
    Route::get('/company/profile/logo', [CompanyProfileController::class, 'logo'])
        ->middleware('permission:company.view')
        ->name('company.logo');

    Route::get('/company/addresses', [CompanyAddressController::class, 'index'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.index');
    Route::get('/company/addresses/create', [CompanyAddressController::class, 'create'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.create');
    Route::post('/company/addresses', [CompanyAddressController::class, 'store'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.store');
    Route::get('/company/addresses/{address}/edit', [CompanyAddressController::class, 'edit'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.edit');
    Route::put('/company/addresses/{address}', [CompanyAddressController::class, 'update'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.update');
    Route::post('/company/addresses/{address}/close', [CompanyAddressController::class, 'close'])
        ->middleware('permission:company.address.manage')
        ->name('company.addresses.close');

    Route::get('/company/document-categories', [DocumentCategoryController::class, 'index'])
        ->middleware('permission:company.document_categories.manage')
        ->name('company.categories.index');
    Route::post('/company/document-categories', [DocumentCategoryController::class, 'store'])
        ->middleware('permission:company.document_categories.manage')
        ->name('company.categories.store');
    Route::put('/company/document-categories/{category}', [DocumentCategoryController::class, 'update'])
        ->middleware('permission:company.document_categories.manage')
        ->name('company.categories.update');

    Route::get('/company/documents', [CompanyDocumentController::class, 'index'])
        ->middleware('permission:company.documents.view')
        ->name('company.documents.index');
    Route::get('/company/documents/expiring', [CompanyDocumentController::class, 'expiring'])
        ->middleware('permission:company.documents.view')
        ->name('company.documents.expiring');
    Route::get('/company/documents/create', [CompanyDocumentController::class, 'create'])
        ->middleware('permission:company.documents.create')
        ->name('company.documents.create');
    Route::post('/company/documents', [CompanyDocumentController::class, 'store'])
        ->middleware('permission:company.documents.create')
        ->name('company.documents.store');
    Route::get('/company/documents/{document}', [CompanyDocumentController::class, 'show'])
        ->middleware('permission:company.documents.view')
        ->name('company.documents.show');
    Route::get('/company/documents/{document}/edit', [CompanyDocumentController::class, 'edit'])
        ->middleware('permission:company.documents.update')
        ->name('company.documents.edit');
    Route::put('/company/documents/{document}', [CompanyDocumentController::class, 'update'])
        ->middleware('permission:company.documents.update')
        ->name('company.documents.update');
    Route::post('/company/documents/{document}/versions', [CompanyDocumentController::class, 'storeVersion'])
        ->middleware('permission:company.documents.update')
        ->name('company.documents.versions.store');
    Route::post('/company/documents/{document}/retire', [CompanyDocumentController::class, 'destroy'])
        ->middleware('permission:company.documents.delete')
        ->name('company.documents.destroy');
    Route::get('/company/documents/{document}/versions/{version}/download', CompanyDocumentDownloadController::class)
        ->middleware('permission:company.documents.download')
        ->name('company.documents.download');
});
