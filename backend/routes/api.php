<?php

use App\Http\Controllers\Api\MunicipalityController;
use App\Http\Controllers\Api\MunicipalityIndicatorController;
use App\Http\Controllers\Api\StateController;
use Illuminate\Support\Facades\Route;

Route::get('/states', [StateController::class, 'index'])->name('states.index');
Route::get('/municipalities', [MunicipalityController::class, 'search'])->name('municipalities.index');
Route::get('/states/{state:ibge_code}/municipalities', [MunicipalityController::class, 'index'])
    ->where('state', '[0-9]{2}')
    ->name('states.municipalities.index');
Route::get('/municipalities/{ibgeCode}/indicators', [MunicipalityIndicatorController::class, 'index'])
    ->name('municipalities.indicators.index');
