<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\TokenVerifyController;
use App\Http\Controllers\Api\V1\Users\UserManagementController;
use App\Http\Controllers\Api\V1\Roles\RoleManagementController;
use App\Http\Controllers\Api\V1\Permissions\PermissionManagementController;
use App\Http\Controllers\Api\V1\ServiceClients\ServiceClientController;
use App\Http\Controllers\Api\V1\AuthRules\AuthRuleController;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // User Authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification-otp', [AuthController::class, 'resendVerificationOtp']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    
    // Service-to-Service Authentication
    Route::post('/token-verify', [TokenVerifyController::class, 'handle']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Authentication Required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Auth Management Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    /*
    |--------------------------------------------------------------------------
    | User Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage users')->prefix('users')->group(function () {
        Route::get('/', [UserManagementController::class, 'index']);
        Route::post('/', [UserManagementController::class, 'store']);
        Route::get('/{user}', [UserManagementController::class, 'show']);
        Route::put('/{user}', [UserManagementController::class, 'update']);
        Route::delete('/{user}', [UserManagementController::class, 'destroy']);
        
        Route::post('/{user}/roles/assign', [UserManagementController::class, 'assignRoles']);
        Route::post('/{user}/roles/remove', [UserManagementController::class, 'removeRoles']);
        Route::post('/{user}/roles/sync', [UserManagementController::class, 'syncRoles']);
        
        Route::post('/{user}/permissions/give', [UserManagementController::class, 'givePermissions']);
        Route::post('/{user}/permissions/revoke', [UserManagementController::class, 'revokePermissions']);
        Route::post('/{user}/permissions/sync', [UserManagementController::class, 'syncPermissions']);
    });

    /*
    |--------------------------------------------------------------------------
    | Role Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage roles')->prefix('roles')->group(function () {
        Route::get('/', [RoleManagementController::class, 'index']);
        Route::post('/', [RoleManagementController::class, 'store']);
        Route::get('/{role}', [RoleManagementController::class, 'show']);
        Route::put('/{role}', [RoleManagementController::class, 'update']);
        Route::delete('/{role}', [RoleManagementController::class, 'destroy']);
        
        Route::post('/{role}/permissions/assign', [RoleManagementController::class, 'assignPermissions']);
        Route::post('/{role}/permissions/revoke', [RoleManagementController::class, 'revokePermissions']);
        Route::post('/{role}/permissions/sync', [RoleManagementController::class, 'syncPermissions']);
    });

    /*
    |--------------------------------------------------------------------------
    | Permission Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage permissions')->prefix('permissions')->group(function () {
        Route::get('/', [PermissionManagementController::class, 'index']);
        Route::post('/', [PermissionManagementController::class, 'store']);
        Route::get('/{permission}', [PermissionManagementController::class, 'show']);
        Route::put('/{permission}', [PermissionManagementController::class, 'update']);
        Route::delete('/{permission}', [PermissionManagementController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Service Client Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage service clients')->prefix('service-clients')->group(function () {
        Route::get('/', [ServiceClientController::class, 'index']);
        Route::post('/', [ServiceClientController::class, 'store']);
        Route::get('/{serviceClient}', [ServiceClientController::class, 'show']);
        Route::put('/{serviceClient}', [ServiceClientController::class, 'update']);
        Route::delete('/{serviceClient}', [ServiceClientController::class, 'destroy']);
        
        Route::post('/{serviceClient}/rotate-token', [ServiceClientController::class, 'rotateToken']);
        Route::post('/{serviceClient}/toggle-status', [ServiceClientController::class, 'toggleStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | Authorization Rules Management Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:manage auth rules')->prefix('auth-rules')->group(function () {
        Route::get('/', [AuthRuleController::class, 'index']);
        Route::post('/', [AuthRuleController::class, 'store']);
        Route::get('/{authRule}', [AuthRuleController::class, 'show']);
        Route::put('/{authRule}', [AuthRuleController::class, 'update']);
        Route::delete('/{authRule}', [AuthRuleController::class, 'destroy']);
        
        Route::get('/services', [AuthRuleController::class, 'getServices']);
        Route::post('/test', [AuthRuleController::class, 'testRule']);
        Route::post('/{authRule}/toggle-status', [AuthRuleController::class, 'toggleStatus']);
    });
});
