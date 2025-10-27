<?php

namespace App\Http\Controllers\Api;

use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller {
    protected $otpService;

    /**
    * Request email change ( send OTP )
    */

    public function requestEmailChange( Request $request ): JsonResponse {
        $validator = Validator::make( $request->all(), [
            'new_email' => 'required|email',
        ] );

        if ( $validator->fails() ) {
            return response()->json( [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422 );
        }

        $user = $request->user();
        $otpService = new OtpService();

        // Send OTP to new email
        $otpService->generateAndSendOtp( $request->new_email, 'email_change' );

        // Store pending email temporarily
        $user->pending_email = $request->new_email;
        $user->save();

        return response()->json( [
            'success' => true,
            'message' => 'OTP sent to the new email address',
        ] );
    }

    /**
    * Verify OTP and confirm email change
    */

    public function verifyEmailChange( Request $request ): JsonResponse {
        $validator = Validator::make( $request->all(), [
            'otp' => 'required|string|size:6',
        ] );

        if ( $validator->fails() ) {
            return response()->json( [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422 );
        }

        $user = $request->user();

        if ( !$user->pending_email ) {
            return response()->json( [
                'success' => false,
                'message' => 'No pending email change request found',
            ], 400 );
        }

        $otpService = new OtpService();

        // Verify OTP for pending email
        $verify = $otpService->verifyOtp( $user->pending_email, $request->otp, 'email_change' );

        if ( !$verify[ 'success' ] ) {
            return response()->json( $verify, 400 );
        }

        // Update and clear pending email
        $user->email = $user->pending_email;
        $user->pending_email = null;
        $user->save();

        return response()->json( [
            'success' => true,
            'message' => 'Email address updated successfully',
            'data' => $user,
        ] );
    }

    public function updatePersonalInfo( Request $request ): JsonResponse {
        $user = $request->user();

        // Validate input
        $validated = $request->validate( [
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'state'      => 'sometimes|string|max:255',
            'country'    => 'sometimes|string|max:255',
        ] );

        // Update user details
        $user->update( $validated );

        return response()->json( [
            'success' => true,
            'message' => 'Personal information updated successfully',
            'data' => $user->only( [ 'first_name', 'last_name', 'state', 'country' ] ),
        ] );
    }
}
