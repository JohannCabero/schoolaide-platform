<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ImportLogController;
use App\Http\Controllers\Api\ServiceRequestController;
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
    Route::patch('tenant/profile', [TenantController::class, 'update']);

    Route::apiResource('students', StudentController::class)
        ->parameters(['students' => 'id'])
        ->except(['destroy']);

    Route::apiResource('service-requests', ServiceRequestController::class)
        ->parameters(['service-requests' => 'id']);
    Route::patch('service-requests/{id}/approve', [ServiceRequestController::class, 'approve']);
    Route::patch('service-requests/{id}/reject', [ServiceRequestController::class, 'reject']);

    Route::get('imports', [ImportLogController::class, 'index']);
    Route::get('imports/{id}', [ImportLogController::class, 'show']);
    Route::post('imports', [ImportLogController::class, 'store']);
});
