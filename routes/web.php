<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This is a pure REST API backend. All application routes live in api.php.
| This file only handles the root fallback for non-API requests.
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'UNIMAR Portfolios API — see /api/health',
    ]);
});
