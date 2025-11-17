<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExternalUserController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/external-users', [ExternalUserController::class, 'index']);
