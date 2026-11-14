<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\OfficeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientImportController;
use App\Http\Controllers\Contracts\ContractController;
use App\Http\Controllers\Dev\DevUiController;
use App\Http\Middleware\EnsureDevToolsEnabled;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/import', [ClientImportController::class, 'create'])->name('clients.import');
    Route::post('clients/import/preview', [ClientImportController::class, 'preview'])->name('clients.import.preview');
    Route::get('clients/import/template', [ClientImportController::class, 'template'])->name('clients.import.template');
    Route::get('clients/import/{token}', [ClientImportController::class, 'showPreview'])
        ->where('token', '[A-Za-z0-9]{40}')
        ->name('clients.import.show');
    Route::post('clients/import/confirm', [ClientImportController::class, 'confirm'])->name('clients.import.confirm');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [ClientController::class, 'show'])->whereNumber('client')->name('clients.show');
    Route::patch('clients/{client}', [ClientController::class, 'update'])->whereNumber('client')->name('clients.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->whereNumber('client')->name('clients.destroy');

    Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::post('contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('contracts/{contract}/attachment', [ContractController::class, 'attachment'])->whereNumber('contract')->name('contracts.attachment');
    Route::patch('contracts/{contract}/status', [ContractController::class, 'status'])->whereNumber('contract')->name('contracts.status');
    Route::get('contracts/{contract}', [ContractController::class, 'show'])->whereNumber('contract')->name('contracts.show');
    Route::patch('contracts/{contract}', [ContractController::class, 'update'])->whereNumber('contract')->name('contracts.update');

    Route::get('offices', [OfficeController::class, 'index'])->name('offices.index');
    Route::post('offices', [OfficeController::class, 'store'])->name('offices.store');
    Route::patch('offices/{office}', [OfficeController::class, 'update'])->name('offices.update');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/password-reset', [UserController::class, 'resetPassword'])->name('users.password-reset');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('audit-log', AuditLogController::class)->name('audit-log.index');
});

Route::middleware([EnsureDevToolsEnabled::class, 'auth', 'verified', 'can:view-dev-tools'])->group(function () {
    Route::get('dev/ui', DevUiController::class)->name('dev.ui');
});

require __DIR__.'/settings.php';
