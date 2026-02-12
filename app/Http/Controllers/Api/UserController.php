<?php

namespace App\Http\Controllers\Api;

use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Services\CartService;
use Illuminate\Support\Str;
use App\Models\Plot;



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

   /**
 * @OA\Post(
 *     path="/api/v1/cart/add",
 *     tags={"Cart"},
 *     summary="Add plot to cart",
 *     description="Adds a plot to the user's cart. 
 *                  Only plots with status 'available' can be added. 
 *                  If the plot is already sold or reserved, the request will fail.",
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"estate_id","plot_id","price"},
 *             @OA\Property(
 *                 property="estate_id",
 *                 type="integer",
 *                 example=1,
 *                 description="ID of the estate where the plot belongs"
 *             ),
 *             @OA\Property(
 *                 property="plot_id",
 *                 type="integer",
 *                 example=10,
 *                 description="ID of the plot to add to cart"
 *             ),
 *             @OA\Property(
 *                 property="price",
 *                 type="number",
 *                 format="float",
 *                 example=3500000,
 *                 description="Price of the plot at the time of adding to cart"
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Plot added to cart successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Plot added to cart"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=5),
 *                 @OA\Property(property="cart_id", type="string", example="b2e5f1b3-9e3c-4b5e-9e11-23caa001aa22"),
 *                 @OA\Property(property="estate_id", type="integer", example=1),
 *                 @OA\Property(property="plot_id", type="integer", example=10),
 *                 @OA\Property(property="price", type="number", example=3500000),
 *                 @OA\Property(property="user_id", type="integer", nullable=true, example=3),
 *                 @OA\Property(property="temporary_user_id", type="string", nullable=true, example="guest-uuid-string"),
 *                 @OA\Property(property="cart_status", type="string", example="active"),
 *                 @OA\Property(property="created_at", type="string", format="date-time"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=400,
 *         description="Plot not available or already in cart",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(
 *                 property="message",
 *                 type="string",
 *                 example="This plot is currently sold and cannot be added to cart."
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=404,
 *         description="Plot not found",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="No query results for model [Plot] 99")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Validation failed",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(
 *                 property="errors",
 *                 type="object",
 *                 example={
 *                     "plot_id": {"The selected plot id is invalid."}
 *                 }
 *             )
 *         )
 *     )
 * )
 */


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

            //  FETCH PLOT
            $plot = Plot::findOrFail($request->plot_id);

            //  CHECK STATUS
            if ($plot->status !== 'available') {
                return response()->json([
                    'success' => false,
                    'message' => "This plot is currently {$plot->status} and cannot be added to cart."
                ], 400);
            }

            //  ADD TO CART
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


    /**
 * @OA\Get(
 *     path="/api/v1/cart",
 *     tags={"Cart"},
 *     summary="Get user cart items",
 *     description="Retrieve all active cart items for the logged-in user or guest session.",
 *
 *     @OA\Response(
 *         response=200,
 *         description="Cart retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="cart_id", type="string", example="b2e5f1b3-9e3c-4b5e-9e11-23caa001aa22"),
 *                     @OA\Property(property="estate_id", type="integer", example=1),
 *                     @OA\Property(property="plot_id", type="integer", example=10),
 *                     @OA\Property(property="price", type="number", example=3500000),
 *                     @OA\Property(property="cart_status", type="string", example="active"),
 *                     @OA\Property(property="created_at", type="string", format="date-time"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
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


    /**
 * @OA\Delete(
 *     path="/api/v1/cart/{id}",
 *     tags={"Cart"},
 *     summary="Remove item from cart",
 *     description="Remove a specific item from the cart.",
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="Cart item ID",
 *         @OA\Schema(type="integer", example=5)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Item removed successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Item removed from cart")
 *         )
 *     )
 * )
 */

    public function removeCartItem(int $id): JsonResponse
    {
        $this->cartService->removeItem($id);

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart'
        ]);
    }

    /**
 * @OA\Get(
 *     path="/api/v1/cart-total",
 *     tags={"Cart"},
 *     summary="Get cart total",
 *     description="Calculate total amount of all active cart items.",
 *
 *     @OA\Response(
 *         response=200,
 *         description="Cart total calculated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="total", type="number", example=10500000)
 *         )
 *     )
 * )
 */


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

    /**
 * @OA\Delete(
 *     path="/api/v1/cart-clear",
 *     tags={"Cart"},
 *     summary="Clear cart",
 *     description="Remove all active items from the user's cart.",
 *
 *     @OA\Response(
 *         response=200,
 *         description="Cart cleared successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Cart cleared")
 *         )
 *     )
 * )
 */

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
