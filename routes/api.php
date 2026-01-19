<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\GeoJsonController;

Route::patch('/v1/geojson/{estate}/layout/{id}', [GeoJsonController::class, 'updateLayoutFeature']);

Route::get('/v1/geojson/debug-files', function () {
    $dir = storage_path('app/private/geojson');
    return response()->json([
        'dir' => $dir,
        'exists' => \Illuminate\Support\Facades\File::exists($dir),
        'files' => \Illuminate\Support\Facades\File::exists($dir)
            ? array_map(fn($f) => basename($f), \Illuminate\Support\Facades\File::files($dir))
            : [],
    ]);
});

require __DIR__.'/api/v1.php';

 