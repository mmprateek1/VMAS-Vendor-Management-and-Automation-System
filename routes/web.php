<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorImportController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('auth')->group(function () {
    Route::view('/account/security', 'security')->middleware('password.confirm')->name('security');
    Route::get('/account/recovery', fn () => view('recovery'))->middleware('password.confirm')->name('recovery');
    Route::middleware('operational')->group(function () {
        Route::get('/dashboard', [WorkspaceController::class, 'dashboard'])->name('dashboard');
        Route::get('/vendors/export', [VendorController::class, 'export'])->middleware('can:vendors.export')->name('vendors.export');
        Route::middleware('can:vendors.manage')->group(function () {
            Route::get('/vendor-imports/template', [VendorImportController::class, 'template'])->name('vendor-imports.template');
            Route::get('/vendor-imports', [VendorImportController::class, 'index'])->name('vendor-imports.index');
            Route::post('/vendor-imports', [VendorImportController::class, 'store'])->middleware('throttle:10,1')->name('vendor-imports.store');
            Route::get('/vendor-imports/{import}', [VendorImportController::class, 'show'])->name('vendor-imports.show');
            Route::post('/vendor-imports/{import}/mapping', [VendorImportController::class, 'mapping'])->name('vendor-imports.mapping');
            Route::get('/vendor-imports/{import}/rows/{row}/edit', [VendorImportController::class, 'edit'])->name('vendor-imports.edit');
            Route::patch('/vendor-imports/{import}/rows/{row}', [VendorImportController::class, 'update'])->name('vendor-imports.update');
            Route::post('/vendor-imports/{import}/commit', [VendorImportController::class, 'commit'])->name('vendor-imports.commit');
            Route::post('/vendor-imports/{import}/cancel', [VendorImportController::class, 'cancel'])->name('vendor-imports.cancel');
            Route::resource('vendors', VendorController::class)->only(['create', 'store', 'edit', 'update']);
        });
        Route::patch('/vendors/{vendor}/activation', [VendorController::class, 'activation'])->middleware('can:vendors.activate')->name('vendors.activation');
        Route::resource('vendors', VendorController::class)->only(['index', 'show'])->middleware('can:vendors.view');
        Route::get('/audit', [WorkspaceController::class, 'audit'])->middleware('can:audit.view')->name('audit');
        Route::get('/settings', [WorkspaceController::class, 'settings'])->middleware('can:settings.view')->name('settings');
        Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:users.manage');
    });
});
