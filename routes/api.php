<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OfficeSyncController;
use App\Http\Controllers\Api\GlAccountSyncController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Controllers\Api\TellerController;

Route::get('/sync/offices', [OfficeSyncController::class, 'sync']);
Route::get('/sync/glaccounts', [GlAccountSyncController::class, 'sync']);

Route::apiResource('vaults', VaultController::class);
Route::apiResource('tellers', TellerController::class);