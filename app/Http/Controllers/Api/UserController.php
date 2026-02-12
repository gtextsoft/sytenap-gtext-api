<?php

namespace App\Http\Controllers\Api;

use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Services\CartService;
use Illuminate\Support\Str;


class UserController extends Controller {
    protected $otpService;
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

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

    /**
    * Resend OTP for email change
    */

    public function resendEmailChangeOtp( Request $request ): JsonResponse {
        try {
            $user = $request->user();

            if ( !$user->pending_email ) {
                return response()->json( [
                    'message' => 'No pending email change request found'
                ], 400 );
            }

            // Generate and send new OTP to the pending email
            $otpService = new OtpService();
            $otpResult = $otpService->generateAndSendOtp(
                $user->pending_email,
                'email_change'
            );

            if ( !$otpResult[ 'success' ] ) {
                return response()->json( [
                    'message' => 'Failed to send OTP',
                    'error' => $otpResult[ 'message' ]
                ], 500 );
            }

            return response()->json( [
                'message' => 'OTP resent successfully',
                'otp_expires_in_minutes' => $otpResult[ 'expires_in_minutes' ]
            ], 200 );

        } catch ( \Exception $e ) {
            return response()->json( [
                'message' => 'Failed to resend OTP',
                'error' => $e->getMessage()
            ], 500 );
        }
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

    private function getTempUserId(): string
    {
        if (!session()->has('temp_user_id')) {
            session(['temp_user_id' => (string) Str::uuid()]);
        }

        return session('temp_user_id');
    }

    public function addToCart(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'estate_id' => 'required|integer|exists:estates,id',
            'plot_id'   => 'required|integer|exists:plots,id',
            'price'     => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {

            $cartItem = $this->cartService->addItem(
                estateId: $request->estate_id,
                plotId: $request->plot_id,
                price: $request->price,
                userId: $request->user()?->id,
                tempUserId: $this->getTempUserId()
            );

            return response()->json([
                'success' => true,
                'message' => 'Plot added to cart',
                'data' => $cartItem
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getCart(Request $request): JsonResponse
    {
        $items = $this->cartService->getCartItems(
            $request->user()?->id,
            $this->getTempUserId()
        );

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    public function removeCartItem(int $id): JsonResponse
    {
        $this->cartService->removeItem($id);

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart'
        ]);
    }

    public function cartTotal(Request $request): JsonResponse
    {
        $total = $this->cartService->getCartTotal(
            $request->user()?->id,
            $this->getTempUserId()
        );

        return response()->json([
            'success' => true,
            'total' => $total
        ]);
    }

    public function clearCart(Request $request): JsonResponse
    {
        $this->cartService->clearCart(
            $request->user()?->id,
            $this->getTempUserId()
        );

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared'
        ]);
    }






}
