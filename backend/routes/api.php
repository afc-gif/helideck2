<?php

use App\Http\Controllers\Api\AuthenticatedSessionController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationMemberController;
use App\Http\Controllers\Api\PlatformRoleController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    try {
        DB::select('select 1');
    } catch (Throwable) {
        return response()->json([
            'status' => 'unavailable',
            'service' => 'artsci-survey-api',
        ], 503);
    }

    return response()->json(['status' => 'ok', 'service' => 'artsci-survey-api']);
})->name('api.health');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.logout');
    Route::get('/me', [CurrentUserController::class, 'show'])->name('api.me');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('api.organizations.index');
    Route::get('/organizations/{publicId}', [OrganizationController::class, 'show'])->name('api.organizations.show');
    Route::get('/organizations/{publicId}/members', [OrganizationMemberController::class, 'index'])
        ->name('api.organizations.members.index');
    Route::get('/platform/roles', [PlatformRoleController::class, 'index'])->name('api.platform.roles.index');
});
