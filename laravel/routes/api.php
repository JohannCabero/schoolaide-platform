<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::post('tenant/register', [TenantController::class, 'registerTenant']);

Route::middleware(['tenant'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'registerUser']);
        Route::post('login', [AuthController::class, 'login']);
    });
});

Route::middleware(['tenant', 'auth:api', 'tenant.user'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('tenant/profile', [TenantController::class, 'profile']);
    Route::patch('tenant/profile', [TenantController::class, 'updateProfile']);

    Route::apiResource('students', StudentController::class)->except(['destroy']);
});
