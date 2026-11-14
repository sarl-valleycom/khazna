<?php

use App\Http\Controllers\Dev\DevUiController;
use App\Http\Middleware\EnsureDevToolsEnabled;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware([EnsureDevToolsEnabled::class, 'auth', 'verified', 'can:view-dev-tools'])->group(function () {
    Route::get('dev/ui', DevUiController::class)->name('dev.ui');
});

require __DIR__.'/settings.php';
