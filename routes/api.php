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


Route::get('/v1/geojson/{estate}/layout', [GeoJsonController::class, 'getLayout']);
Route::get('/v1/geojson/{estate}/boundary', [GeoJsonController::class, 'getBoundary']);

Route::patch('/v1/geojson/{estate}/layout/{id}', [GeoJsonController::class, 'updateLayoutFeature']);


require __DIR__.'/api/v1.php';

 