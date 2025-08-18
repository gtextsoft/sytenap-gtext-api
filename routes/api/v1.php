<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EstateController;


Route::prefix('v1')->group(function () {

    Route::get('test', function () {
        return response()->json(['status' => true, 'message' => "API v1 is up and running"], 200);
    });


    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('login',[AuthController::class, 'login']);
    });

    Route::prefix('estate')->group(function () {
        Route::post('media', [EstateController::class, 'media_store']);
        Route::post('new', [EstateController::class, 'store']);
        Route::get('/estates/top-rated', [EstateController::class, 'getTopRatedEstates']);
        Route::get('/estates/top-rated-alt', [EstateController::class, 'getTopRatedEstatesAlternative']);
    });

});