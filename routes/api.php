<?php

use App\Http\Controllers\Api\V1\CreateCoAdminController;
use App\Http\Controllers\Api\V1\CreateSpecialistController;
use App\Http\Controllers\Api\V1\DeactivateUserController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ReactivateUserController;
use App\Http\Controllers\Api\V1\UpdateSpecialistController;
use App\Http\Controllers\Api\V1\UpdateUserPermissionsController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Registered with the `api/v1` prefix in bootstrap/app.php.
| No database access is allowed here before Gate C.
|
*/

Route::get('/health', function () {
    return ApiResponse::success([
        'status' => 'ok',
        'application' => 'basma-noor',
        'version' => '1.0.0',
    ], 'Service is healthy');
});

// Every authenticated route also rejects inactive accounts (D-055), even when
// the request carries a token that was issued before deactivation.
Route::middleware(['auth:api', EnsureUserIsActive::class])->group(function () {
    Route::get('/me', MeController::class);
    Route::post('/logout', LogoutController::class);

    Route::middleware('can:createCoAdmin,'.User::class)
        ->post('/admins', CreateCoAdminController::class);

    Route::middleware('can:createSpecialist,'.User::class)
        ->post('/specialists', CreateSpecialistController::class);

    Route::middleware('can:updateSpecialist,specialist')
        ->patch('/specialists/{specialist}', UpdateSpecialistController::class);

    Route::middleware('can:deactivateUser,user')
        ->post('/users/{user}/deactivate', DeactivateUserController::class);

    Route::middleware('can:reactivateUser,user')
        ->post('/users/{user}/reactivate', ReactivateUserController::class);

    Route::middleware('can:manageUserPermissions,user')
        ->put('/users/{user}/permissions', UpdateUserPermissionsController::class);
});
