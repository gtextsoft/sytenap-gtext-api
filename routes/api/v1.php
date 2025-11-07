<?php
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EstateController;
use App\Http\Controllers\Api\PlotController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FaqController;

Route::prefix('v1')->group(function () {

    Route::get('test', function () {
        return response()->json(['status' => true, 'message' => "API v1 is up and running"], 200);
    });
    Route::prefix('agent')->group(function () {
        Route::post('/login', [AuthController::class, 'agent_login']);
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
        Route::get('/estates/detail', [EstateController::class, 'getTopRatedEstatesWithAvailability']);
        Route::post('/estates/nearby', [EstateController::class, 'getNearbyEstates']);
        Route::post('/estates/search', [EstateController::class, 'filterSearch']);
        Route::get('/detail/{estateId}', [EstateController::class, 'EstateDetails']);
        Route::post('/{estateId}/generate-plots', [PlotController::class, 'generatePlots']);
        Route::get('/{estateId}/plots', [PlotController::class, 'getPlotsByEstate']);

        // Preview purchase of plots
        Route::post('/plots/preview-purchase', [PlotController::class, 'previewPurchase']);
        // Purchase plots
        Route::post('/plots/purchase', [PlotController::class, 'finalizePurchase'])->middleware('auth:sanctum');
      
        // Get all estates
         Route::get('/estates/all', [EstateController::class, 'getAllEstates']);
        
    });

    Route::prefix('estate-plot-details')->group(function () {
        Route::post('plot-detail', [EstateController::class, 'plot_detail']);
        Route::get('all', [EstateController::class, 'index']);
        Route::get('/estate/{estateId}', [EstateController::class, 'getByEstate']);
        Route::put('/{id}', [EstateController::class, 'update']);
        Route::delete('/{id}', [EstateController::class, 'destroy']);
    });

    Route::prefix('myproperties')->group(function () {
          Route::get('/customer-metrics', [PlotController::class, 'getCustomerMetrics'])->middleware('auth:sanctum');
          Route::get('/customer-properties', [PlotController::class, 'getCustomerProperties'])->middleware('auth:sanctum');
    });


    Route::prefix('user')->group(function () {
        Route::get('/account', [AuthController::class, 'index']);
        Route::post('/email/request-change', [UserController::class, 'requestEmailChange'])->middleware('auth:sanctum');
        Route::post('/email/verify-change', [UserController::class, 'verifyEmailChange'])->middleware('auth:sanctum');
    });

    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::post('/allocate-property', [PlotController::class, 'allocateProperty'])->middleware('auth:sanctum');
    });

    Route::get('/payments/callback', [PlotController::class, 'handlePaystackCallback']);


// Document Routes
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::post('/documents/upload', [DocumentController::class, 'upload']);
    Route::put('/documents/{id}/publish', [DocumentController::class, 'publish']);
    Route::put('/documents/{id}/unpublish', [DocumentController::class, 'unpublish']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
});

// Public routes
Route::get('/documents', [DocumentController::class, 'index']);
Route::get('/documents/{id}/download', [DocumentController::class, 'download']);


    // FAQ routes
   // Route::apiResource('faqs', FaqController::class);

});
