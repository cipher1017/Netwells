<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'landing'])->name('portal.landing');
Route::post('/purchase', [PortalController::class, 'purchase'])->name('portal.purchase');
Route::get('/status', [PortalController::class, 'status'])->name('portal.status');
