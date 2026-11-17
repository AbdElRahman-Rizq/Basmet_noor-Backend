<?php

use App\Http\Controllers\Api\V1\CreateCoAdminController;
use App\Http\Controllers\Api\V1\LogoutController;
use App\Http\Controllers\Api\V1\MeController;
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

Route::middleware('auth:api')->group(function () {
    Route::get('/me', MeController::class);
    Route::post('/logout', LogoutController::class);
});

Route::middleware(['auth:api', 'can:createCoAdmin,'.User::class])
    ->post('/admins', CreateCoAdminController::class);
