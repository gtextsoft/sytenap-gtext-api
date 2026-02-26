<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExternalUserController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/external-users', [ExternalUserController::class, 'index']);

Route::get('/login', function () {
    return redirect('https://portal.gtextland.com/sign-in');
});
