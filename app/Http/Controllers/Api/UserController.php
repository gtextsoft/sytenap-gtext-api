<?php

namespace App\Http\Controllers\Api;

use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Services\CartService;
use App\Services\InvoiceService;
use Illuminate\Support\Str;
use App\Models\Plot;
use App\Models\Invoice; 
use App\Services\ZohoService;
use App\Models\ZohoCredential;
use App\Models\Cart;
use App\Models\Referral;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;



class UserController extends Controller {
    protected $otpService;
    protected $cartService;
    protected $invoiceService;

    public function __construct(CartService $cartService, InvoiceService $invoiceService)
    {
        $this->cartService = $cartService;
        $this->invoiceService = $invoiceService;
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
 *     summary="Add or update plot in cart",
 *     description="Adds a plot to the cart or updates an existing cart item. 
 *                  If the plot already exists in the cart, the system updates the amount or payment_type. 
 *                  Only plots with status 'available' can be added.",
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"estate_id","plot_id","price"},
 *
 *             @OA\Property(
 *                 property="estate_id",
 *                 type="integer",
 *                 example=1,
 *                 description="ID of the estate"
 *             ),
 *
 *             @OA\Property(
 *                 property="plot_id",
 *                 type="integer",
 *                 example=10,
 *                 description="Plot ID"
 *             ),
 *
 *             @OA\Property(
 *                 property="price",
 *                 type="number",
 *                 format="float",
 *                 example=3500000,
 *                 description="Plot price"
 *             ),
 *
 *             @OA\Property(
 *                 property="amount",
 *                 type="number",
 *                 format="float",
 *                 nullable=true,
 *                 example=1000000,
 *                 description="Amount customer wants to pay (optional)"
 *             ),
 *
 *             @OA\Property(
 *                 property="payment_type",
 *                 type="string",
 *                 enum={"full","installmental"},
 *                 nullable=true,
 *                 example="installmental",
 *                 description="Payment type"
 *             ),
 *
 *             @OA\Property(
 *                 property="temporary_user_id",
 *                 type="string",
 *                 nullable=true,
 *                 example="f4740c0e-9011-4761-8485-f0c605f3e720",
 *                 description="Temporary UUID for guest users"
 *             ),
 *
 *             @OA\Property(
 *                 property="user_id",
 *                 type="integer",
 *                 nullable=true,
 *                 example=3,
 *                 description="Authenticated user ID"
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Cart updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Cart item updated"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=400,
 *         description="Invalid request",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="This plot is currently sold and cannot be added to cart.")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Validation error",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(property="errors", type="object")
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

        'amount' => 'nullable|numeric|min:0',
        'payment_type' => 'nullable|in:full,installmental',

        'temporary_user_id' => 'nullable|string|uuid',
        'user_id' => 'nullable'

    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }

    try {

        // FETCH PLOT
        $plot = Plot::findOrFail($request->plot_id);

        // CHECK PLOT STATUS
        if ($plot->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => "This plot is currently {$plot->status} and cannot be added to cart."
            ], 400);
        }

        $userId = $request->user_id ?? null;
        $tempUserId = $request->temporary_user_id ?? $this->getTempUserId();

        // VALIDATE FULL PAYMENT RULE
        if ($request->payment_type === 'full') {

            $amount = $request->amount ?? $request->price;

            if ($amount != $request->price) {
                return response()->json([
                    'success' => false,
                    'message' => 'For full payment, amount must equal the plot price.'
                ], 422);
            }
        }

        // CHECK IF ITEM EXISTS IN CART
        $existingItem = Cart::where('plot_id', $request->plot_id)
            ->where(function ($query) use ($userId, $tempUserId) {

                if ($userId) {
                    $query->where('user_id', $userId);
                }

                if ($tempUserId) {
                    $query->orWhere('temporary_user_id', $tempUserId);
                }

            })->first();

        // UPDATE EXISTING CART ITEM
        if ($existingItem) {

            $updateData = [];

            if ($request->has('amount')) {
                $updateData['amount'] = $request->amount;
            }

            if ($request->has('payment_type')) {
                $updateData['payment_type'] = $request->payment_type;
            }

            if ($request->has('price')) {
                $updateData['price'] = $request->price;
            }

            $existingItem->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Cart item updated',
                'data' => $existingItem
            ]);
        }

        // ADD NEW ITEM
        $cartItem = $this->cartService->addItem(
            estateId: $request->estate_id,
            plotId: $request->plot_id,
            price: $request->price,
            userId: $userId,
            tempUserId: $tempUserId,
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
        if($request->has('user_id'))
        {
            
            $items = $this->cartService->getCartItems(
             $request->user_id,
                $this->getTempUserId()
            );

        }else{

            $items = $this->cartService->getCartItems(
            $request->user()?->id,
            $request->temporary_user_id
        );

        }
        
       return response()->json([
        'success' => true,
        'data' => $items
        ], 200, [], JSON_UNESCAPED_UNICODE);
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
            $tempUserId = $request->header('X-Temp-User')
        );

        return response()->json([
            'success' => true,
            'total' => number_format($total, 2)
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
            $tempUserId = $request->temporary_user_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared'
        ]);
    }


    /**
 * @OA\Post(
 *     path="/api/v1/create-invoice",
 *     operationId="createInvoice",
 *     tags={"Checkout"},
 *     summary="Create invoice from cart items",
 *     description="Creates an invoice from the authenticated user's cart or a guest cart using X-Temp-User header. Optionally allows adding one or multiple plots to the cart before generating invoice.",
 *
 *     @OA\Parameter(
 *         name="X-Temp-User",
 *         in="header",
 *         required=false,
 *         description="Temporary user UUID for guest checkout. Required if user is not authenticated.",
 *         @OA\Schema(
 *             type="string",
 *             format="uuid",
 *             example="f4740c0e-9011-4761-8485-f0c605f3e720"
 *         )
 *     ),
 *
 *     @OA\RequestBody(
 *         required=false,
 *         description="Optional payload to add one or multiple plots to cart before generating invoice. Only processed if plot_id is provided.",
 *         @OA\JsonContent(
 *             type="object",
 *
 *             @OA\Property(
 *                 property="estate_id",
 *                 type="integer",
 *                 example=5,
 *                 description="Estate ID containing the plots"
 *             ),
 *
 *             @OA\Property(
 *                 property="plot_id",
 *                 type="array",
 *                 description="Array of plot IDs to add to cart",
 *                 @OA\Items(type="integer", example=102)
 *             ),
 *
 *             @OA\Property(
 *                 property="price",
 *                 type="number",
 *                 format="float",
 *                 example=3500000,
 *                 description="Total price for the selected plot(s)"
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Invoice created successfully",
 *         @OA\JsonContent(
 *             type="object",
 *
 *             @OA\Property(
 *                 property="success",
 *                 type="boolean",
 *                 example=true
 *             ),
 *
 *             @OA\Property(
 *                 property="message",
 *                 type="string",
 *                 example="Invoice created successfully"
 *             ),
 *
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *
 *                 @OA\Property(
 *                     property="invoice",
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", example=12),
 *                     @OA\Property(property="user_id", type="integer", nullable=true, example=3),
 *                     @OA\Property(property="invoice_number", type="string", example="INV-20260212-5F3A2B"),
 *                     @OA\Property(property="payment_status", type="string", example="pending"),
 *                     @OA\Property(property="amount", type="number", format="float", example=3500000),
 *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-12T10:00:00Z"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-12T10:00:00Z")
 *                 ),
 *
 *                 @OA\Property(
 *                     property="bank_info",
 *                     type="object",
 *                     @OA\Property(property="bank_name", type="string", example="Providus Bank | Access Bank"),
 *                     @OA\Property(property="account_name", type="string", example="GtextLand Limited"),
 *                     @OA\Property(property="account_number", type="string", example="1308305323 | 1497602357"),
 *                     @OA\Property(property="reference", type="string", example="INV-20260212-5F3A2B")
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=400,
 *         description="No items found in cart",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="No items in cart")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Unauthenticated.")
 *         )
 *     )
 * )
 */

    public function createInvoice(Request $request): JsonResponse
    {
        $user = $request->user();
        $tempUserId = $request->header('X-Temp-User'); // if guest passed it in header

        if($request->has('estate_id') && $request->has('plot_id') && $request->has('price'))
        {
           foreach ($request->plot_id as $plotId) {
                $this->cartService->addItem(
                    estateId: $request->estate_id,
                    plotId: $plotId,
                    price: $request->price,
                    userId: $user?->id,
                    tempUserId: null
                );
            }
        }else
        {

        }

        if($request->has('agent_referral_id'))
        {
            $agent = Referral::where('referral_code', $request->agent_referral_id)->first();
            if(!$agent)
            {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid agent referral code'
                ], 400);
            }   

            $agentId = $agent->user_id ?? null;

        }   else {
            $agentId = null;
        }
        // Get cart items
        $cartItems = $this->cartService->getCartItems($user?->id, $tempUserId);

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items in cart'
            ], 400);
        }

        //  Calculate total
        $totalAmount = $this->cartService->getCartTotal($user?->id, $tempUserId);


        //  Create invoice
        $invoice = $this->invoiceService->createInvoice($user?->id ?? null, $totalAmount, $paymentStatus = 'pending', $agentId);

        $cart_updated = $this->cartService->markCartAsCheckedOut($user?->id, $invoice->invoice_number);

        // Return demo bank info for frontend
        $bankInfo = [
            'bank_name' => 'Providus Bank | Access Bank',
            'account_name' => 'GtextLand Limited',
            'account_number' => '1308305323 | 1497602357',
            'reference' => $invoice->invoice_number,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully',
            'data' => [
                'invoice' => $invoice,
                'bank_info' => $bankInfo,
            ]
        ]);
    }

    

   
    

    // public function confirmPayment(Request $request, Invoice $invoice)
    // {
    //     if ($invoice->payment_status === 'paid') {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Invoice already marked as paid'
    //         ], 400);
    //     }

    //     try {

    //         // 1️⃣ Mark invoice as paid
    //         $invoice->update([
    //             'payment_status' => 'paid'
    //         ]);

    //         // 2 Get user (optional if invoice allows null)
    //         $user = $invoice->user;

    //         // 3️⃣ Get Zoho refresh token from DB
    //         $zohoCredential = ZohoCredential::first();

    //         if (!$zohoCredential || !$zohoCredential->refresh_token) {
    //             throw new \Exception('Zoho CRM is not connected. Missing refresh token.');
    //         }

    //         $refreshToken = $zohoCredential->refresh_token;

    //        $cart = Cart::where('cart_id', $invoice->invoice_number)->first();

    //         $estate_title = $cart->estate->title;


    //         // 4️ Send to Zoho CRM
    //         $zohoService = new ZohoService();

        
    //         // create contact and get Zoho contact ID
    //     $contactId = $zohoService->getOrCreateClient([
    //         "Name" => $user?->first_name ?? '',
    //         'Last_Name'  => $user?->last_name ?? 'Customer',
    //         'First_Name' => $user?->first_name ?? '',
    //         'Email'      => $user?->email ?? '',
    //         'Estate' => $estate_title,
    //         'Company'      => 'Gtext Land Limited', 
    //     ], $refreshToken);

    //     // create deal using the contact ID
    //     $deal = $zohoService->createDeal([
    //         'Deal_Name'   => 'Property Purchase - ' . $invoice->invoice_number,
    //         'Full_Name'   => $user?->first_name . ' ' . $user?->last_name,
    //         'Company'      => 'Gtext Land Limited', 
    //         'Email'       => $user?->email ?? '',
    //         'Amount'      => $invoice->amount,
    //         'Stage'       => 'Payment Made',
    //         'Description' => 'Customer confirmed payment via bank transfer',
    //         'Estate' => $estate_title,
    //         'Invoice_Number' => $invoice->invoice_number,
    //         'Payment_Status' => 'pending',
    //         'Agent_ID' => $invoice->agent_id,
    //     ], $contactId);


    //     $payment = $zohoService->createEstatePayment([
    //                 'Name'   => 'Property Purchase - ' . $invoice->invoice_number,
    //                 'Client_Name'   => $user?->first_name . ' ' . $user?->last_name,
    //                 'First_Name' => $user?->first_name ?? '',
    //                 'Last_Name'  => $user?->last_name ?? 'Customer',
    //                 'Phone' => $user?->phone ?? '',
    //                 'Email'       => $user?->email ?? '',
    //                 'Estate_Name' => $estate_title,
    //                 'Invoice_Number' => $invoice->invoice_number,
    //                 'Amount_Paid'      => $invoice->amount,
    //                 'Payment_Status' => 'pending',
    //                 'Agent_ID' => $invoice->agent_id,
    //             ]); 
        


    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Payment confirmed and sent to CRM',
    //             'data' => [
    //                 'invoice' => $invoice,
    //                 'zoho' => $payment,
    //                 'deal' => $deal,
    //             ]
    //         ]);

    //     } catch (\Exception $e) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Payment confirmed but CRM sync failed',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
 * @OA\Post(
 *     path="/api/v1/invoices/{invoice}/confirm-payment",
 *     tags={"Invoices"},
 *     summary="Confirm invoice payment, upload proof and send to Zoho CRM",
 *     description="Marks invoice as paid, allows customer upload proof of payment and sends estate purchase data to Zoho CRM. 
 *                  One invoice can contain multiple estate plots from cart.",
 *
 *     @OA\Parameter(
 *         name="invoice",
 *         in="path",
 *         required=true,
 *         description="Invoice ID",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 @OA\Property(
 *                     property="payment_proof",
 *                     type="string",
 *                     format="binary",
 *                     description="Proof of payment (PDF or image)"
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Payment confirmed successfully"
 *     ),
 *
 *     security={{"sanctum":{}}}
 * )
 */

public function confirmPayment(Request $request, Invoice $invoice)
{

    if ($invoice->payment_status === 'paid') {
        return response()->json([
            'success' => false,
            'message' => 'Invoice already marked as paid'
        ], 400);
    }

    try {

        $paymentProofUrl = null;

        /*
        |--------------------------------------------------------------------------
        | Upload Payment Proof (Cloudinary)
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('payment_proof')) {

            $file = $request->file('payment_proof');

            $uploadResult = Cloudinary::uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => 'payment_proofs',
                    'resource_type' => 'auto',
                ]
            );

            $paymentProofUrl = $uploadResult['secure_url'];
        }

        /*
        |--------------------------------------------------------------------------
        | Mark Invoice Paid
        |--------------------------------------------------------------------------
        */

        $invoice->update([
            'payment_status' => 'paid',
            'payment_proof' => $paymentProofUrl
        ]);

        /*
        |--------------------------------------------------------------------------
        | Get User
        |--------------------------------------------------------------------------
        */

        $user = $invoice->user;

        /*
        |--------------------------------------------------------------------------
        | Get All Cart Items using Invoice Number
        |--------------------------------------------------------------------------
        */

        $cartItems = Cart::where('cart_id', $invoice->invoice_number)->get();

        if ($cartItems->isEmpty()) {
            throw new \Exception('No cart items found for this invoice');
        }

        /*
        |--------------------------------------------------------------------------
        | Extract Estate Data
        |--------------------------------------------------------------------------
        */

        $estateData = [];
        $estateNames = [];

        foreach ($cartItems as $item) {

            $estateData[] = [
                'estate_id' => $item->estate_id,
                'plot_id' => $item->plot_id,
                'amount' => $item->amount
            ];

            if ($item->estate) {
                $estateNames[] = $item->estate->title;
            }
        }

        $estateNamesString = implode(', ', $estateNames);

        /*
        |--------------------------------------------------------------------------
        | Zoho Credentials
        |--------------------------------------------------------------------------
        */

        $zohoCredential = ZohoCredential::first();

        if (!$zohoCredential || !$zohoCredential->refresh_token) {
            throw new \Exception('Zoho CRM is not connected. Missing refresh token.');
        }

        $refreshToken = $zohoCredential->refresh_token;

        $zohoService = new ZohoService();

        /*
        |--------------------------------------------------------------------------
        | Create / Get Zoho Contact
        |--------------------------------------------------------------------------
        */

        $contactId = $zohoService->getOrCreateClient([
            "Name" => $user?->first_name ?? '',
            'Last_Name'  => $user?->last_name ?? 'Customer',
            'First_Name' => $user?->first_name ?? '',
            'Email'      => $user?->email ?? '',
            'Estate' => $estateNamesString,
            'Company' => 'Gtext Land Limited',
        ], $refreshToken);


        /*
        |--------------------------------------------------------------------------
        | Create Zoho Deal
        |--------------------------------------------------------------------------
        */

        $deal = $zohoService->createDeal([
            'Deal_Name' => 'Property Purchase - ' . $invoice->invoice_number,
            'Full_Name' => $user?->first_name . ' ' . $user?->last_name,
            'Company' => 'Gtext Land Limited',
            'Email' => $user?->email ?? '',
            'Amount' => $invoice->amount, // total invoice amount
            'Stage' => 'Payment Made',
            'Description' => 'Customer confirmed payment via bank transfer',
            'Estate' => $estateNamesString,
            'Invoice_Number' => $invoice->invoice_number,
            'Payment_Status' => 'pending',
            'Agent_ID' => $invoice->agent_id,
            'Estate_Items' => json_encode($estateData)
        ], $contactId);


        /*
        |--------------------------------------------------------------------------
        | Create Estate Payment Record in Zoho
        |--------------------------------------------------------------------------
        */

        $payment = $zohoService->createEstatePayment([
            'Name' => 'Property Purchase - ' . $invoice->invoice_number,
            'Client_Name' => $user?->first_name . ' ' . $user?->last_name,
            'First_Name' => $user?->first_name ?? '',
            'Last_Name' => $user?->last_name ?? 'Customer',
            'Phone' => $user?->phone ?? '',
            'Email' => $user?->email ?? '',
            'Estate_Name' => $estateNamesString,
            'Invoice_Number' => $invoice->invoice_number,
            'Amount_Paid' => $invoice->amount,
            'Payment_Status' => 'pending',
            'Agent_ID' => $invoice->agent_id,
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Payment confirmed and sent to CRM',
            'data' => [
                'invoice' => $invoice,
                'estates' => $estateData,
                'zoho_payment' => $payment,
                'deal' => $deal
            ]
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => 'Payment confirmed but CRM sync failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function getInvoices(Request $request)
    {
        $user_id = $request->user()->id;
        $invoices = Invoice::where('user_id', $user_id)
                    ->where('payment_status', 'pending')
                    ->get();

        return response()->json([
                'success' => true,
                'message' => 'Customer Invoices',
                'data' => $invoices
            ]);
    }



}
