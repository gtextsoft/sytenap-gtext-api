<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PlotController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\AdminReferralController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\EstateController;
use App\Http\Controllers\Api\AdminClientController;
use App\Http\Controllers\Api\CommissionSettingController;
use App\Http\Controllers\Api\CommissionWithdrawalController; 


// API v1 routes
Route::prefix('v1')->group(function () {

    // -------------------------
    // Test route
    // -------------------------
    Route::get('test', fn() => response()->json([
        'status' => true,
        'message' => "API v1 is up and running"
    ], 200));

    // -------------------------
    // Agent Routes
    // -------------------------
    Route::prefix('agent')->group(function () {
        Route::post('/login', [AuthController::class, 'agent_login']);
        Route::post('/balance', [AgentController::class, 'balance']);
        Route::post('/commission-history', [AgentController::class, 'history']);

        //  NEW - AGENT WITHDRAWAL ROUTES
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/withdraw', [CommissionWithdrawalController::class, 'requestWithdrawal']);
            Route::get('/withdrawals', [CommissionWithdrawalController::class, 'agentWithdrawals']);
        });
    });

    // -------------------------
    // Auth Routes
    // -------------------------
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::post('login', [AuthController::class, 'login']);
    });

    // -------------------------
    // User Routes (protected)
    // -------------------------
    Route::prefix('user')->middleware('auth:sanctum')->group(function () {
        Route::get('/account', [AuthController::class, 'index']);
        Route::post('/email/request-change', [UserController::class, 'requestEmailChange']);
        Route::post('/email/verify-change', [UserController::class, 'verifyEmailChange']);
    });

    // -------------------------
    // Estate & Plot Routes
    // -------------------------
    Route::prefix('estate')->group(function () {
        Route::prefix('estates')->group(function () {
            Route::get('top-rated', [EstateController::class, 'getTopRatedEstates']);
            Route::get('top-rated-alt', [EstateController::class, 'getTopRatedEstatesAlternative']);
            Route::get('detail', [EstateController::class, 'getTopRatedEstatesWithAvailability']);
            Route::post('nearby', [EstateController::class, 'getNearbyEstates']);
            Route::post('search', [EstateController::class, 'filterSearch']);
            Route::get('all', [EstateController::class, 'getAllEstates']);
        });
        Route::post('media', [EstateController::class, 'media_store']);
        Route::post('new', [EstateController::class, 'store']);
        Route::post('plots/preview-purchase', [PlotController::class, 'previewPurchase']);
        Route::post('plots/purchase', [PlotController::class, 'finalizePurchase'])->middleware('auth:sanctum');
        Route::get('detail/{estateId}', [EstateController::class, 'EstateDetails']);
        Route::post('{estateId}/generate-plots', [PlotController::class, 'generatePlots']);
        Route::get('{estateId}/plots', [PlotController::class, 'getPlotsByEstate']);    
    });

    // -------------------------
    // Estate-Plot Details (Admin)
    // -------------------------
    Route::prefix('estate-plot-details')->middleware('auth:sanctum')->group(function () {
        Route::post('plot-detail', [EstateController::class, 'plot_detail']);
        Route::get('all', [EstateController::class, 'index']);
        Route::get('estate/{estateId}', [EstateController::class, 'getByEstate']);
        Route::put('{id}', [EstateController::class, 'update']);
        Route::delete('{id}', [EstateController::class, 'destroy']);
    });

    // -------------------------
    // My Properties (User)
    // -------------------------
    Route::prefix('myproperties')->middleware('auth:sanctum')->group(function () {
        Route::get('customer-metrics', [PlotController::class, 'getCustomerMetrics']);
        Route::get('customer-properties', [PlotController::class, 'getCustomerProperties']);
    });

   
    Route::prefix('document')->group(function () {
        Route::get('/my-document', [DocumentController::class, 'getUserDocument'])->middleware('auth:sanctum');
        Route::get('/all-document', [DocumentController::class, 'getAllUserDocument']);
    });
    
    // Admin documents (protected)
    Route::prefix('admin/documents')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('upload', [DocumentController::class, 'store']);
        Route::put('{id}/publish', [DocumentController::class, 'publish']);
        Route::put('{id}/unpublish', [DocumentController::class, 'unpublish']);
        Route::delete('{id}', [DocumentController::class, 'destroy']);
    });

    // Public documents
    Route::get('documents', [DocumentController::class, 'index']);
    Route::get('documents/{id}/download', [DocumentController::class, 'download']);

    // -------------------------
    // Admin Routes
    // -------------------------
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('allocate-property', [PlotController::class, 'allocateProperty']);
        Route::post('reset-client-password', [AdminClientController::class, 'resetClientPassword']);
        Route::get('referrals', [AdminReferralController::class, 'index']);
        Route::get('commission-settings', [CommissionSettingController::class, 'index']);
        Route::post('commission-settings', [CommissionSettingController::class, 'store']);
        Route::post('commission-settings/{id}/toggle', [CommissionSettingController::class, 'toggleStatus']);

        // 🔥 NEW - ADMIN WITHDRAWAL ROUTES
        Route::get('/withdrawals', [CommissionWithdrawalController::class, 'allWithdrawals']);
        Route::post('/withdrawals/{id}/approve', [CommissionWithdrawalController::class, 'approve']);
        Route::post('/withdrawals/{id}/reject', [CommissionWithdrawalController::class, 'reject']);
    });

    Route::prefix('property')->group(function () {
        Route::post('/sync-for-resale', [PlotController::class, 'syncForResale'])->middleware('auth:sanctum');
    });

    Route::get('/payments/callback', [PlotController::class, 'handlePaystackCallback']);

    // -------------------------
    // FAQ Routes
    // -------------------------
    Route::apiResource('faqs', FaqController::class);

    // -------------------------
    // Payment Callback
    // -------------------------
    Route::post('payments/callback', [PlotController::class, 'handlePaystackCallback']);
});
