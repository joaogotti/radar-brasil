<?php

use App\Http\Controllers\Api\StateController;
use Illuminate\Support\Facades\Route;

Route::get('/states', [StateController::class, 'index'])->name('states.index');
