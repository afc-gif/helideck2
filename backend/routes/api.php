<?php

use App\Http\Controllers\Api\AuthenticatedSessionController;
use App\Http\Controllers\Api\ChecklistAdministrationController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationMemberController;
use App\Http\Controllers\Api\PlatformOrganizationController;
use App\Http\Controllers\Api\PlatformRoleController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\SurveyInstanceController;
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
Route::get('/registration/organizations', [RegistrationController::class, 'organizations'])
    ->name('api.registration.organizations');
Route::post('/register', [RegistrationController::class, 'store'])
    ->middleware('throttle:registration')
    ->name('api.register');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.logout');
    Route::get('/me', [CurrentUserController::class, 'show'])->name('api.me');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('api.organizations.index');
    Route::get('/organizations/{publicId}', [OrganizationController::class, 'show'])->name('api.organizations.show');
    Route::get('/organizations/{publicId}/members', [OrganizationMemberController::class, 'index'])
        ->name('api.organizations.members.index');
    Route::post('/organizations/{publicId}/members/{userId}/approve', [OrganizationMemberController::class, 'approve'])
        ->whereNumber('userId')
        ->name('api.organizations.members.approve');
    Route::get('/organizations/{publicId}/checklists', [ChecklistAdministrationController::class, 'index'])
        ->name('api.organizations.checklists.index');
    Route::post('/organizations/{publicId}/checklists', [ChecklistAdministrationController::class, 'store'])
        ->name('api.organizations.checklists.store');
    Route::get('/organizations/{publicId}/checklists/{checklistPublicId}', [ChecklistAdministrationController::class, 'show'])
        ->name('api.organizations.checklists.show');
    Route::patch('/organizations/{publicId}/checklists/{checklistPublicId}', [ChecklistAdministrationController::class, 'update'])
        ->name('api.organizations.checklists.update');
    Route::post('/organizations/{publicId}/checklists/{checklistPublicId}/versions', [ChecklistAdministrationController::class, 'createVersion'])
        ->name('api.organizations.checklists.versions.store');
    Route::put('/organizations/{publicId}/checklists/{checklistPublicId}/versions/{versionNumber}', [ChecklistAdministrationController::class, 'saveVersion'])
        ->whereNumber('versionNumber')
        ->name('api.organizations.checklists.versions.update');
    Route::post('/organizations/{publicId}/checklists/{checklistPublicId}/versions/{versionNumber}/publish', [ChecklistAdministrationController::class, 'publishVersion'])
        ->whereNumber('versionNumber')
        ->name('api.organizations.checklists.versions.publish');
    Route::get('/organizations/{publicId}/survey-templates', [SurveyInstanceController::class, 'templates'])
        ->name('api.organizations.survey-templates.index');
    Route::get('/organizations/{publicId}/surveys', [SurveyInstanceController::class, 'index'])
        ->name('api.organizations.surveys.index');
    Route::post('/organizations/{publicId}/surveys', [SurveyInstanceController::class, 'store'])
        ->name('api.organizations.surveys.store');
    Route::get('/organizations/{publicId}/surveys/{surveyPublicId}', [SurveyInstanceController::class, 'show'])
        ->name('api.organizations.surveys.show');
    Route::put('/organizations/{publicId}/surveys/{surveyPublicId}/answers', [SurveyInstanceController::class, 'saveAnswers'])
        ->name('api.organizations.surveys.answers.update');
    Route::get('/platform/roles', [PlatformRoleController::class, 'index'])->name('api.platform.roles.index');
    Route::get('/platform/organization-requests', [PlatformOrganizationController::class, 'index'])
        ->name('api.platform.organization-requests.index');
    Route::post('/platform/organization-requests/{publicId}/approve', [PlatformOrganizationController::class, 'approve'])
        ->name('api.platform.organization-requests.approve');
});
