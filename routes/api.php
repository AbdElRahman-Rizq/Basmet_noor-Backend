<?php

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
    return response()->json([
        'status' => 'ok',
        'application' => 'basma-noor',
        'version' => '1.0.0',
    ]);
});
