<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Estate;
use App\Models\Plot;
use App\Models\PlotPurchase;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\CustomerProperty;


class PlotController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/estate/{estateId}/generate-plots",
     *     tags={"Plot Management"},
     *     summary="Generate plots for an estate",
     *     description="Generate plots for a given estate based on its available plots. 
     *                  Each plot will be assigned a unique plot_id, coordinates, and availability status.",
     *     @OA\Parameter(
     *         name="estateId",
     *         in="path",
     *         required=true,
     *         description="The ID of the estate to generate plots for",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Plots generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Generated 50 plots for estate: Beryl Estate Lagos"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="estate_id", type="integer", example=1),
     *                     @OA\Property(property="plot_id", type="string", example="BERYL-001"),
     *                     @OA\Property(property="coordinate", type="string", example="6.5119104, 3.6348072"),
     *                     @OA\Property(property="status", type="string", enum={"available", "sold", "reserved"}, example="available"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-03T10:25:36.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-03T10:25:36.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No available plots to generate",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No available plots to generate.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Estate not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [Estate] 99")
     *         )
     *     )
     * )
     */
    public function generatePlots($estateId)
    {
        $estate = Estate::with('plotDetail')->findOrFail($estateId);

        // Number of plots to generate comes from available_plot
        $availablePlots = $estate->plotDetail->available_plot ?? 0;

        if ($availablePlots <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No available plots to generate.'
            ], 400);
        }

        // Estate size (e.g. "500sqm") -> use to guide spacing
        $estateSize = (int) filter_var($estate->size, FILTER_SANITIZE_NUMBER_INT);
        $plotSize   = 500; // sqm per plot

        // how many meters per plot roughly (square root of sqm gives length of side)
        $plotSideMeters = sqrt($plotSize); // ~22.36m
        // convert to degrees offset (approx: 1 deg lat ~ 111,000m)
        $latOffset = $plotSideMeters / 111000; // north-south
        $lngOffset = $plotSideMeters / 111000; // east-west

        // base coordinate
        [$baseLat, $baseLng] = array_map('floatval', explode(',', $estate->cordinates));

        $plotsPerRow = ceil(sqrt($availablePlots)); // make grid square-ish
        $prefix = strtoupper(substr(str_replace(' ', '', $estate->title), 0, 5)); // e.g. BERYL

        $createdPlots = [];

        for ($i = 1; $i <= $availablePlots; $i++) {
            $row = floor(($i - 1) / $plotsPerRow);
            $col = ($i - 1) % $plotsPerRow;

            $lat = $baseLat + ($row * $latOffset);
            $lng = $baseLng + ($col * $lngOffset);

            $plotId = $prefix . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);

            $plot = Plot::create([
                'estate_id'  => $estate->id,
                'plot_id'    => $plotId,
                'coordinate' => "{$lat}, {$lng}",
                'status'     => 'available'
            ]);

            $createdPlots[] = $plot;
        }

        return response()->json([
            'success' => true,
            'message' => "Generated {$availablePlots} plots for estate: {$estate->title}",
            'data'    => $createdPlots
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/estate/{estateId}/plots",
     *     tags={"Plot Management"},
     *     summary="Get all plots for an estate",
     *     description="Retrieve all plots belonging to a specific estate by estate ID",
     *     @OA\Parameter(
     *         name="estateId",
     *         in="path",
     *         required=true,
     *         description="The ID of the estate",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of plots retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="estate", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Beryl Estate Lagos"),
     *                 @OA\Property(property="size", type="string", example="500sqm"),
     *                 @OA\Property(property="town_or_city", type="string", example="Ibeju-Lekki"),
     *                 @OA\Property(property="state", type="string", example="Lagos")
     *             ),
     *             @OA\Property(
     *                 property="plots",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="plot_id", type="string", example="BERYL-001"),
     *                     @OA\Property(property="coordinate", type="string", example="6.5119104, 3.6348072"),
     *                     @OA\Property(property="status", type="string", enum={"available", "sold", "reserved"}, example="available"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-03T10:25:36.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-03T10:25:36.000000Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Estate not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Estate not found")
     *         )
     *     )
     * )
     */
    public function getPlotsByEstate($estateId)
    {
        $estate = Estate::find($estateId);

        if (!$estate) {
            return response()->json([
                'success' => false,
                'message' => 'Estate not found'
            ], 404);
        }

        $plots = $estate->plots; // relationship in Estate model: hasMany(Plot::class)

        return response()->json([
            'success' => true,
            'estate' => [
                'id' => $estate->id,
                'title' => $estate->title,
                'size' => $estate->size,
                'town_or_city' => $estate->town_or_city,
                'state' => $estate->state,
            ],
            'plots' => $plots
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/v1/estate/plots/preview-purchase",
     *     tags={"Plot Purchase"},
     *     summary="Preview purchase of selected plots",
     *     description="Generate a preview of the pricing and details for selected plots in an estate before buying",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"estate_id", "plots", "installment_months"},
     *             @OA\Property(property="estate_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="plots",
     *                 type="array",
     *                 @OA\Items(type="integer", example=1),
     *                 description="Array of plot IDs the customer wants to buy"
     *             ),
     *             @OA\Property(property="installment_months", type="integer", minimum=1, maximum=12, example=6)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Purchase preview generated successfully"
     *     )
     * )
     */
    public function previewPurchase(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'estate_id' => 'required|integer|exists:estates,id',
            'plots' => 'required|array|min:1',
            'plots.*' => 'integer|exists:plots,id',
            'installment_months' => 'required|integer|min:1|max:12'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $estate = Estate::with('plotDetail')->find($request->estate_id);
        $plots = Plot::whereIn('id', $request->plots)
                    ->where('estate_id', $estate->id)
                    ->where('status', 'available')
                    ->get();

        if ($plots->count() !== count($request->plots)) {
            return response()->json([
                'success' => false,
                'message' => 'Some selected plots are not available'
            ], 400);
        }

        // Effective pricing
        $effectivePrice = $estate->plotDetail->promotion_price ?? $estate->plotDetail->price_per_plot;

        $plotsCount = $plots->count();
        $totalPrice = $effectivePrice * $plotsCount;
        $installmentMonths = $request->installment_months;
        $monthlyPayment = round($totalPrice / $installmentMonths, 2);

        // Generate payment schedule
        $paymentSchedule = [];
        $startDate = Carbon::now();

        for ($i = 1; $i <= $installmentMonths; $i++) {
            $paymentSchedule[] = [
                'month' => $i,
                'due_date' => $startDate->copy()->addMonths($i - 1)->format('Y-m-d'),
                'amount' => $monthlyPayment
            ];
        }

        return response()->json([
            'success' => true,
            'estate' => [
                'id' => $estate->id,
                'title' => $estate->title,
                'town_or_city' => $estate->town_or_city,
                'state' => $estate->state,
                'size' => $estate->size,
            ],
            'plots' => $plots,
            'pricing' => [
                'price_per_plot' => $estate->plotDetail->price_per_plot,
                'promotion_price' => $estate->plotDetail->promotion_price,
                'effective_price' => $effectivePrice,
                'plots_selected' => $plotsCount,
                'total_price' => $totalPrice,
                'installment_months' => $installmentMonths,
                'monthly_payment' => $monthlyPayment,
                'payment_schedule' => $paymentSchedule
            ]
        ]);
    }

      
    // public function finalizePurchase(Request $request)
    // {
    //     $validator = \Validator::make($request->all(), [
    //         'estate_id' => 'required|integer|exists:estates,id',
    //         'plots' => 'required|array|min:1',
    //         'plots.*' => 'integer|exists:plots,id',
    //         'installment_months' => 'required|integer|min:1|max:12',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Validation error',
    //             'errors' => $validator->errors(),
    //         ], 422);
    //     }

    //     $estate = Estate::with('plotDetail')->find($request->estate_id);
    //     $plots = Plot::whereIn('id', $request->plots)
    //                 ->where('estate_id', $estate->id)
    //                 ->where('status', 'available')
    //                 ->lockForUpdate()
    //                 ->get();

    //     if ($plots->count() !== count($request->plots)) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Some selected plots are not available'
    //         ], 400);
    //     }

    //     // Pricing
    //     $effectivePrice = $estate->plotDetail->promotion_price ?? $estate->plotDetail->price_per_plot;
    //     $plotsCount = $plots->count();
    //     $totalPrice = $effectivePrice * $plotsCount;
    //     $installmentMonths = $request->installment_months;
    //     $monthlyPayment = round($totalPrice / $installmentMonths, 2);


    //     $customer_id = $request->user()->id ?? null; // if using auth
    //     if (!$customer_id) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Authentication required to finalize purchase'
    //         ], 401);
    //     }

    //     $customer_email = $request->user()->email ?? null; // if using auth
    //     if (!$customer_email) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Customer email is required for payment processing'
    //         ], 400);
    //     }

    //     // Payment schedule
    //     $paymentSchedule = [];
    //     $startDate = now();
    //     for ($i = 1; $i <= $installmentMonths; $i++) {
    //         $paymentSchedule[] = [
    //             'month' => $i,
    //             'due_date' => $startDate->copy()->addMonths($i - 1)->format('Y-m-d'),
    //             'amount' => $monthlyPayment
    //         ];
    //     }

    //     // Generate Paystack Reference
    //     $paymentReference = 'PLOT-' . Str::upper(Str::random(10));

    //     // Call Paystack API (first installment or full payment)
    //     $paystackResponse = Http::withToken(env('PAYSTACK_SECRET_KEY'))
    //         ->post('https://api.paystack.co/transaction/initialize', [
    //             'email' => $customer_email,
    //             'amount' => $monthlyPayment * 100, // kobo
    //             'reference' => $paymentReference,
    //             'callback_url' => env('PAYSTACK_CALLBACK_URL', url('/api/v1/payments/callback')),
    //             'metadata' => [
    //                 'estate_id' => $estate->id,
    //                 'plots' => $plots->pluck('id')->toArray(),
    //                 'installments' => $installmentMonths
    //             ]
    //         ]);

    //     if (!$paystackResponse->successful()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to initialize payment',
    //             'error' => $paystackResponse->json()
    //         ], 500);
    //     }

    //     $paymentData = $paystackResponse->json()['data'];
    //     $paymentLink = $paymentData['authorization_url'];

    //     // Save Purchase + Update Plots
    //     DB::transaction(function () use ($request, $estate, $plots, $totalPrice, $installmentMonths, $monthlyPayment, $paymentSchedule, $paymentReference, $paymentLink) {
    //         PlotPurchase::create([
    //             'estate_id' => $estate->id,
    //             'user_id' => $request->user()->id,
    //             'plots' => $plots->pluck('id')->toArray(),
    //             'total_price' => $totalPrice,
    //             'installment_months' => $installmentMonths,
    //             'monthly_payment' => $monthlyPayment,
    //             'payment_schedule' => $paymentSchedule,
    //             'payment_reference' => $paymentReference,
    //             'payment_link' => $paymentLink,
    //             'payment_status' => 'pending'
    //         ]);

    //         foreach ($plots as $plot) {
    //             $plot->update(['status' => 'sold']);
    //         }
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Purchase confirmed. Proceed to payment.',
    //         'estate' => [
    //             'id' => $estate->id,
    //             'title' => $estate->title,
    //             'town_or_city' => $estate->town_or_city,
    //             'state' => $estate->state,
    //         ],
    //         'plots' => $plots,
    //         'pricing' => [
    //             'total_price' => $totalPrice,
    //             'installment_months' => $installmentMonths,
    //             'monthly_payment' => $monthlyPayment,
    //             'payment_schedule' => $paymentSchedule
    //         ],
    //         'payment' => [
    //             'reference' => $paymentReference,
    //             'link' => $paymentLink,
    //             'status' => 'pending'
    //         ]
    //     ]);
    // }

    // public function handlePaystackCallback(Request $request)
    // {
    //     $reference = $request->query('reference');

    //     if (!$reference) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Payment reference missing',
    //         ], 400);
    //     }

    //     try {
    //         // Verify payment with Paystack
    //         $verifyResponse = Http::withToken(env('PAYSTACK_SECRET_KEY'))
    //             ->get("https://api.paystack.co/transaction/verify/{$reference}");

    //         if (!$verifyResponse->successful()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Failed to verify payment',
    //                 'error' => $verifyResponse->json(),
    //             ], 500);
    //         }

    //         $data = $verifyResponse->json('data');

    //         // Find the purchase record
    //         $purchase = PlotPurchase::where('payment_reference', $reference)->first();

    //         if (!$purchase) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Purchase record not found for reference',
    //             ], 404);
    //         }

    //         $status = $data['status'] === 'success' ? 'paid' : 'failed';

    //         // Update purchase record
    //         $purchase->update([
    //             'payment_status' => $status,
    //             'payment_verified_at' => now(),
    //         ]);

    //         // If payment is successful, create customer property record
    //         if ($status === 'paid') {
    //             CustomerProperty::create([
    //                 'user_id' => $purchase->user_id,
    //                 'estate_id' => $purchase->estate_id,
    //                 'plots' => $purchase->plots,
    //                 'total_price' => $purchase->total_price,
    //                 'installment_months' => $purchase->installment_months,
    //                 'payment_status' => $purchase->installment_months > 1 ? 'outstanding' : 'fully_paid',
    //                 'acquisition_status' => 'held', // can later be marked 'transferred'
    //             ]);
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Payment processed successfully',
    //             'data' => $data,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Payment verification failed',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    
    /**
     * @OA\Post(
     *     path="/api/v1/estate/plots/purchase",
     *     tags={"Estate Plots"},
     *     summary="Finalize and purchase estate plots",
     *     description="Confirms customer selection of plots, calculates pricing, determines payment type (installment or full), generates a payment schedule (if installment), initializes a Paystack transaction, reserves plots (mark as sold), and returns payment details including link and reference.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"estate_id","plots","payment_type"},
     *             @OA\Property(property="estate_id", type="integer", example=12, description="The ID of the estate."),
     *             @OA\Property(
     *                 property="plots",
     *                 type="array",
     *                 description="Array of plot IDs to purchase",
     *                 @OA\Items(type="integer", example=45)
     *             ),
     *             @OA\Property(
     *                 property="payment_type",
     *                 type="string",
     *                 enum={"installment","full"},
     *                 example="installment",
     *                 description="Type of payment selected by the buyer. If 'installment', 'installment_months' is required."
     *             ),
     *             @OA\Property(
     *                 property="installment_months",
     *                 type="integer",
     *                 minimum=1,
     *                 maximum=12,
     *                 example=6,
     *                 description="Number of months for installment payments (1–12). Required only if payment_type is 'installment'."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Purchase confirmed and Paystack payment link generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase initialized successfully. Proceed to payment."),
     *             @OA\Property(
     *                 property="estate",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="title", type="string", example="Palm Gardens Estate"),
     *                 @OA\Property(property="town_or_city", type="string", example="Lekki"),
     *                 @OA\Property(property="state", type="string", example="Lagos")
     *             ),
     *             @OA\Property(
     *                 property="plots",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     example={"id": 45, "plot_number": "PG-45", "status": "sold"}
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pricing",
     *                 type="object",
     *                 @OA\Property(property="total_price", type="number", example=12000000),
     *                 @OA\Property(property="installment_months", type="integer", example=6),
     *                 @OA\Property(property="monthly_payment", type="number", example=2000000),
     *                 @OA\Property(
     *                     property="payment_schedule",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         example={"month": 1, "due_date": "2025-10-03", "amount": 2000000}
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="payment",
     *                 type="object",
     *                 @OA\Property(property="reference", type="string", example="PLOT-AB12CD34EF"),
     *                 @OA\Property(property="link", type="string", example="https://checkout.paystack.com/abcd1234"),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Some selected plots are unavailable or payment initialization failed"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error in request payload"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to initialize Paystack payment"
     *     )
     * )
     */
    public function finalizePurchase(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'estate_id' => 'required|integer|exists:estates,id',
            'plots' => 'required|array|min:1',
            'plots.*' => 'integer|exists:plots,id',
            'installment_months' => 'required|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required to finalize purchase',
            ], 401);
        }

        $estate = Estate::with('plotDetail')->find($request->estate_id);

        if (!$estate || !$estate->plotDetail) {
            return response()->json([
                'success' => false,
                'message' => 'Estate or plot details not found',
            ], 404);
        }

        // Lock available plots
        $plots = Plot::whereIn('id', $request->plots)
            ->where('estate_id', $estate->id)
            ->where('status', 'available')
            ->lockForUpdate()
            ->get();

        if ($plots->count() !== count($request->plots)) {
            return response()->json([
                'success' => false,
                'message' => 'Some selected plots are no longer available',
            ], 400);
        }

        // Determine pricing
        $effectivePrice = $estate->plotDetail->promotion_price ?? $estate->plotDetail->price_per_plot;
        $plotsCount = $plots->count();
        $totalPrice = $effectivePrice * $plotsCount;
        $installmentMonths = $request->installment_months;
        $monthlyPayment = round($totalPrice / $installmentMonths, 2);

        // Build payment schedule
        $paymentSchedule = [];
        $startDate = now();
        for ($i = 1; $i <= $installmentMonths; $i++) {
            $paymentSchedule[] = [
                'month' => $i,
                'due_date' => $startDate->copy()->addMonths($i - 1)->format('Y-m-d'),
                'amount' => $monthlyPayment,
            ];
        }

        // Generate Paystack reference
        $paymentReference = 'PLOT-' . Str::upper(Str::random(10));

        // Initialize payment with Paystack (first installment)
        $paystackResponse = Http::withToken(env('PAYSTACK_SECRET_KEY'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $monthlyPayment * 100, // kobo
                'reference' => $paymentReference,
                'callback_url' => env('PAYSTACK_CALLBACK_URL', url('/api/v1/payments/callback')),
                'metadata' => [
                    'estate_id' => $estate->id,
                    'plots' => $plots->pluck('id')->toArray(),
                    'installments' => $installmentMonths,
                ],
            ]);

        if (!$paystackResponse->successful() || empty($paystackResponse->json('data.authorization_url'))) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment with Paystack',
                'error' => $paystackResponse->json(),
            ], 500);
        }

        $paymentData = $paystackResponse->json('data');
        $paymentLink = $paymentData['authorization_url'];

        // Save purchase record and mark plots as sold
        DB::transaction(function () use ($request, $estate, $plots, $totalPrice, $installmentMonths, $monthlyPayment, $paymentSchedule, $paymentReference, $paymentLink, $user) {
            PlotPurchase::create([
                'estate_id' => $estate->id,
                'user_id' => $user->id,
                'plots' => $plots->pluck('id')->toArray(),
                'total_price' => $totalPrice,
                'installment_months' => $installmentMonths,
                'monthly_payment' => $monthlyPayment,
                'payment_schedule' => $paymentSchedule,
                'payment_reference' => $paymentReference,
                'payment_link' => $paymentLink,
                'payment_status' => 'pending',
            ]);

            foreach ($plots as $plot) {
                $plot->update(['status' => 'available']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Purchase initialized successfully. Proceed to payment.',
            'estate' => [
                'id' => $estate->id,
                'title' => $estate->title,
                'town_or_city' => $estate->town_or_city,
                'state' => $estate->state,
            ],
            'plots' => $plots,
            'pricing' => [
                'total_price' => $totalPrice,
                'installment_months' => $installmentMonths,
                'monthly_payment' => $monthlyPayment,
                'payment_schedule' => $paymentSchedule,
            ],
            'payment' => [
                'reference' => $paymentReference,
                'link' => $paymentLink,
                'status' => 'pending',
            ],
        ]);
    }

    /**
     * Handle Paystack payment callback
     */
    public function handlePaystackCallback(Request $request)
    {
            $reference = $request->query('reference');

            if (!$reference) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment reference missing',
                ], 400);
            }

            try {
                // Verify transaction with Paystack
                $verifyResponse = Http::withToken(env('PAYSTACK_SECRET_KEY'))
                    ->get("https://api.paystack.co/transaction/verify/{$reference}");

                if (!$verifyResponse->successful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to verify payment with Paystack',
                        'error' => $verifyResponse->json(),
                    ], 500);
                }

                $data = $verifyResponse->json('data');
                $status = $data['status'] === 'success' ? 'paid' : 'failed';

                // Locate purchase record
                $purchase = PlotPurchase::where('payment_reference', $reference)->first();
                if (!$purchase) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No matching purchase record found',
                    ], 404);
                }

                // Update purchase record
                $purchase->update([
                    'payment_status' => $status,
                    'payment_verified_at' => now(),
                ]);

                // If payment succeeded, assign property and mark plots as sold
                if ($status === 'paid') {
                    // Mark plots as sold
                    $plotIds = is_array($purchase->plots) ? $purchase->plots : json_decode($purchase->plots, true);

                    if (!empty($plotIds)) {
                        Plot::whereIn('id', $plotIds)->update(['status' => 'sold']);
                    }

                    // Assign property to customer
                    CustomerProperty::create([
                        'user_id' => $purchase->user_id,
                        'estate_id' => $purchase->estate_id,
                        'plots' => $purchase->plots,
                        'total_price' => $purchase->total_price,
                        'installment_months' => $purchase->installment_months,
                        'payment_status' => $purchase->installment_months > 1 ? 'outstanding' : 'fully_paid',
                        'acquisition_status' => 'held',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'data' => [
                        'reference' => $reference,
                        'status' => $status,
                        'gateway_response' => $data,
                    ],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }



        /**
         * @OA\Get(
         *     path="/api/v1/myproperties/customer-metrics",
         *     tags={"Estate Plots"},
         *     summary="Get customer property purchase metrics",
         *     description="Returns summary metrics for the authenticated customer, including the number of purchased properties, held (active installment) properties, and total outstanding payment amount.",
         *     security={{"bearerAuth":{}}},
         *
         *     @OA\Response(
         *         response=200,
         *         description="Customer property metrics retrieved successfully",
         *         @OA\JsonContent(
         *             @OA\Property(property="success", type="boolean", example=true),
         *             @OA\Property(property="message", type="string", example="Metrics fetched successfully."),
         *             @OA\Property(
         *                 property="data",
         *                 type="object",
         *                 @OA\Property(property="purchased_properties", type="integer", example=5, description="Number of fully paid and owned properties."),
         *                 @OA\Property(property="held_properties", type="integer", example=2, description="Number of properties currently on installment or reserved but not fully paid."),
         *                 @OA\Property(property="total_outstanding_payment", type="number", example=3500000, description="Total remaining balance across installment purchases.")
         *             )
         *         )
         *     ),
         *
         *     @OA\Response(
         *         response=401,
         *         description="Authentication required"
         *     ),
         *     @OA\Response(
         *         response=500,
         *         description="Failed to retrieve customer metrics"
         *     )
         * )
         */
    public function getCustomerMetrics(Request $request)
    {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Fully paid purchases
            $purchased = PlotPurchase::where('user_id', $user->id)
                ->where('payment_status', 'paid')
                ->count();

            // Active (on installment or pending)
            $heldPurchases = PlotPurchase::where('user_id', $user->id)
                ->whereIn('payment_status', ['pending', 'outstanding'])
                ->get();

            // Count of held properties
            $held = $heldPurchases->count();

            // Dynamically calculate outstanding payment
            $outstanding = $heldPurchases->sum(function ($purchase) {
                $amountPaid = $purchase->amount_paid ?? 0;
                return max(0, $purchase->total_price - $amountPaid);
            });

            return response()->json([
                'success' => true,
                'message' => 'Metrics fetched successfully.',
                'data' => [
                    'purchased_properties' => $purchased,
                    'held_properties' => $held,
                    'total_outstanding_payment' => $outstanding,
                ],
            ]);
        }


        /**
     * @OA\Get(
     *     path="/api/v1/myproperties/customer-properties",
     *     summary="Get Customer Purchased Properties",
     *     description="Returns a list of all properties purchased by the authenticated customer, grouped by payment status (full paid, outstanding, held). Each property includes related estate, plot, and media details.",
     *     operationId="getCustomerProperties",
     *     tags={"Customer Properties"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with properties grouped by status",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="summary",
     *                 type="object",
     *                 @OA\Property(property="total_purchased", type="integer", example=3, description="Total number of fully paid properties"),
     *                 @OA\Property(property="held_properties", type="integer", example=1, description="Number of held (reserved) properties"),
     *                 @OA\Property(property="total_outstanding_amount", type="number", format="float", example=1500000, description="Sum of all outstanding balances")
     *             ),
     *             @OA\Property(
     *                 property="properties",
     *                 type="object",
     *                 @OA\Property(
     *                     property="full_paid",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="purchase_id", type="integer", example=5),
     *                         @OA\Property(property="payment_status", type="string", example="paid"),
     *                         @OA\Property(property="total_price", type="number", format="float", example=2500000),
     *                         @OA\Property(property="outstanding_balance", type="number", format="float", example=0),
     *                         @OA\Property(property="installment_months", type="integer", example=null),
     *                         @OA\Property(property="payment_schedule", type="object", nullable=true),
     *                         @OA\Property(property="purchase_date", type="string", format="date-time", example="2025-10-08T13:45:16.000000Z"),
     *                         @OA\Property(
     *                             property="plots",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="id", type="integer", example=12),
     *                                 @OA\Property(property="plot_number", type="string", example="A12"),
     *                                 @OA\Property(property="coordinate", type="string", example="6.4567,3.6789"),
     *                                 @OA\Property(property="status", type="string", example="sold")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="estate",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="title", type="string", example="Lekki Royal Estate"),
     *                             @OA\Property(property="town_or_city", type="string", example="Lekki"),
     *                             @OA\Property(property="state", type="string", example="Lagos"),
     *                             @OA\Property(
     *                                 property="plot_detail",
     *                                 type="object",
     *                                 @OA\Property(property="price_per_plot", type="number", format="float", example=2500000),
     *                                 @OA\Property(property="promotion_price", type="number", format="float", example=2200000),
     *                                 @OA\Property(property="available_plot", type="integer", example=15)
     *                             ),
     *                             @OA\Property(
     *                                 property="media",
     *                                 type="object",
     *                                 @OA\Property(property="photos", type="array", @OA\Items(type="string", example="https://example.com/media/photo1.jpg")),
     *                                 @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string", example="https://example.com/media/3d1.jpg")),
     *                                 @OA\Property(property="virtual_tour_video_url", type="string", example="https://example.com/virtual-tour.mp4")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="outstanding",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="purchase_id", type="integer", example=2),
     *                         @OA\Property(property="payment_status", type="string", example="pending"),
     *                         @OA\Property(property="total_price", type="number", example=3000000),
     *                         @OA\Property(property="outstanding_balance", type="number", example=1500000),
     *                         @OA\Property(property="installment_months", type="integer", example=6),
     *                         @OA\Property(property="payment_schedule", type="object", nullable=true),
     *                         @OA\Property(property="purchase_date", type="string", format="date-time", example="2025-10-05T15:35:12.000000Z"),
     *                         @OA\Property(
     *                             property="plots",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="id", type="integer", example=7),
     *                                 @OA\Property(property="plot_number", type="string", example="B5"),
     *                                 @OA\Property(property="coordinate", type="string", example="6.487,3.608"),
     *                                 @OA\Property(property="status", type="string", example="allocated")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="estate",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=9),
     *                             @OA\Property(property="title", type="string", example="Emerald Garden Estate"),
     *                             @OA\Property(property="town_or_city", type="string", example="Ibeju-Lekki"),
     *                             @OA\Property(property="state", type="string", example="Lagos"),
     *                             @OA\Property(
     *                                 property="media",
     *                                 type="object",
     *                                 @OA\Property(property="photos", type="array", @OA\Items(type="string", example="https://example.com/img1.jpg")),
     *                                 @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string", example="https://example.com/3d1.jpg")),
     *                                 @OA\Property(property="virtual_tour_video_url", type="string", example="https://example.com/tour.mp4")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="held",
     *                     type="array",
     *                     description="List of held or reserved properties",
     *                     @OA\Items(ref="#/components/schemas/PropertyItem")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized — when no valid authentication token is provided",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Authentication required")
     *         )
     *     )
     * )
     *
     * @OA\Schema(
     *     schema="PropertyItem",
     *     type="object",
     *     @OA\Property(property="purchase_id", type="integer", example=11),
     *     @OA\Property(property="payment_status", type="string", example="held"),
     *     @OA\Property(property="total_price", type="number", example=2500000),
     *     @OA\Property(property="outstanding_balance", type="number", example=null),
     *     @OA\Property(property="purchase_date", type="string", format="date-time", example="2025-10-08T13:45:16Z"),
     *     @OA\Property(property="plots", type="array", @OA\Items(
     *         @OA\Property(property="id", type="integer", example=14),
     *         @OA\Property(property="plot_number", type="string", example="A15"),
     *         @OA\Property(property="coordinate", type="string", example="6.45,3.67"),
     *         @OA\Property(property="status", type="string", example="reserved")
     *     )),
     *     @OA\Property(property="estate", type="object",
     *         @OA\Property(property="id", type="integer", example=3),
     *         @OA\Property(property="title", type="string", example="Cedar Court Estate"),
     *         @OA\Property(property="state", type="string", example="Lagos"),
     *         @OA\Property(property="media", type="object",
     *             @OA\Property(property="photos", type="array", @OA\Items(type="string", example="https://example.com/pic.jpg")),
     *             @OA\Property(property="third_dimension_model_images", type="array", @OA\Items(type="string", example="https://example.com/3d.jpg")),
     *             @OA\Property(property="virtual_tour_video_url", type="string", example="https://example.com/tour.mp4")
     *         )
     *     )
     * )
     */

        public function getCustomerProperties(Request $request)
        {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // eager load estate + nested relations
            $purchases = PlotPurchase::with(['estate.plotDetail', 'estate.media'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            // collect all plot IDs used across purchases to fetch once
            $allPlotIds = $purchases->flatMap(function ($p) {
                $plots = $p->plots;
                if (is_string($plots)) {
                    $decoded = json_decode($plots, true);
                    $plots = is_array($decoded) ? $decoded : [];
                }
                return $plots ?: [];
            })->filter()->unique()->values()->all();

            $plotsById = collect([]);
            if (!empty($allPlotIds)) {
                $plotsById = Plot::whereIn('id', $allPlotIds)->get()->keyBy(function ($item) {
                    return (string)$item->id;
                });
            }

            $fullyPaid = [];
            $outstanding = [];
            $held = [];

            foreach ($purchases as $purchase) {
                // normalize plots array
                $plotsArray = $purchase->plots;
                if (is_string($plotsArray)) {
                    $decoded = json_decode($plotsArray, true);
                    $plotsArray = is_array($decoded) ? $decoded : [];
                }
                $plotsArray = $plotsArray ?: [];

                // build plot details list
                $plotDetails = [];
                foreach ($plotsArray as $pid) {
                    $pModel = $plotsById->get((string)$pid);
                    $plotDetails[] = [
                        'id' => $pModel->id ?? $pid,
                        'plot_number' => $pModel->plot_id ?? null,
                        'coordinate' => $pModel->coordinate ?? null,
                        'status' => $pModel->status ?? null,
                    ];
                }

                // estate (may be null if estate deleted)
                $estate = $purchase->estate;
                $estateData = [
                    'id' => $estate->id ?? null,
                    'title' => $estate->title ?? $estate->name ?? null,
                    'town_or_city' => $estate->town_or_city ?? null,
                    'state' => $estate->state ?? null,
                    'plot_detail' => $estate && $estate->plotDetail ? [
                        'price_per_plot' => $estate->plotDetail->price_per_plot ?? null,
                        'promotion_price' => $estate->plotDetail->promotion_price ?? null,
                        'available_plot' => $estate->plotDetail->available_plot ?? null,
                    ] : null,
                    'media' => $estate && $estate->media ? [
                        'photos' => $estate->media->photos ?? [],
                        'third_dimension_model_images' => $estate->media->third_dimension_model_images ?? [],
                        'virtual_tour_video_url' => $estate->media->virtual_tour_video_url ?? null,
                    ] : [
                        'photos' => [],
                        'third_dimension_model_images' => [],
                        'virtual_tour_video_url' => null,
                    ],
                ];

                // money fields - use correct DB column names
                $totalPrice = isset($purchase->total_price) ? (float)$purchase->total_price : null;
                $amountPaid = isset($purchase->amount_paid) ? (float)$purchase->amount_paid : 0.0;

                // If you have a payments relationship, you can compute amountPaid:
                // $amountPaid = $purchase->payments()->sum('amount');

                $outstandingBalance = null;
                if (!is_null($totalPrice)) {
                    $outstandingBalance = max(0, $totalPrice - $amountPaid);
                }

                $item = [
                    'purchase_id' => $purchase->id,
                    'payment_status' => $purchase->payment_status, 
                    'total_price' => $totalPrice,
                    //'amount_paid' => $amountPaid,
                    'outstanding_balance' => $outstandingBalance,
                    'installment_months' => $purchase->installment_months ?? null,
                    'payment_schedule' => $purchase->payment_schedule ?? null,
                    'purchase_date' => $purchase->created_at ? $purchase->created_at->toISOString() : null,
                    'plots' => $plotDetails,
                    'estate' => $estateData,
                ];

                // group by status
                if ($purchase->payment_status === 'paid') {
                    $fullyPaid[] = $item;
                } elseif (in_array($purchase->payment_status, ['pending', 'outstanding'])) {
                    $outstanding[] = $item;
                } else {
                    // treat other states (eg 'held', 'reserved') as held
                    $held[] = $item;
                }
            }

            $totalOutstanding = collect($outstanding)->sum(function ($i) {
                return $i['outstanding_balance'] ?? 0;
            });

            $summary = [
                'total_purchased' => count($fullyPaid),
                'held_properties' => count($held),
                'total_outstanding_amount' => $totalOutstanding,
            ];

            return response()->json([
                'summary' => $summary,
                'properties' => [
                    'full_paid' => $fullyPaid,
                    'outstanding' => $outstanding,
                    'held' => $held,
                ],
            ], 200);
        }

        /**
     * @OA\Post(
     *     path="/api/v1/admin/allocate-property",
     *     tags={"Admin - Property Management"},
     *     summary="Allocate property to a customer (Admin Only)",
     *     description="Allows admin to manually allocate plots to a customer. This can be used for special allocations, 
     *                  gifts, promotional offers, or administrative corrections. The plots will be marked as sold 
     *                  and a CustomerProperty record will be created.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "estate_id", "plots"},
     *             @OA\Property(
     *                 property="user_id",
     *                 type="integer",
     *                 example=25,
     *                 description="The ID of the customer to allocate property to"
     *             ),
     *             @OA\Property(
     *                 property="estate_id",
     *                 type="integer",
     *                 example=12,
     *                 description="The ID of the estate"
     *             ),
     *             @OA\Property(
     *                 property="plots",
     *                 type="array",
     *                 description="Array of plot IDs to allocate",
     *                 @OA\Items(type="integer", example=45)
     *             ),
     *             @OA\Property(
     *                 property="total_price",
     *                 type="number",
     *                 example=5000000,
     *                 description="Total price for the allocation (optional, will be calculated if not provided)"
     *             ),
     *             @OA\Property(
     *                 property="installment_months",
     *                 type="integer",
     *                 minimum=0,
     *                 maximum=12,
     *                 example=6,
     *                 description="Number of installment months (0 or null for full payment)"
     *             ),
     *             @OA\Property(
     *                 property="payment_status",
     *                 type="string",
     *                 enum={"paid", "pending", "outstanding"},
     *                 example="paid",
     *                 description="Payment status for this allocation"
     *             ),
     *             @OA\Property(
     *                 property="acquisition_status",
     *                 type="string",
     *                 enum={"held", "transferred"},
     *                 example="held",
     *                 description="Acquisition status (default: held)"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Property allocated successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or plots unavailable"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Admin access required"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User, estate, or plots not found"
     *     )
     * )
     */
    public function allocateProperty(Request $request)
    {
        // Verify admin authentication (uncomment when ready)
        // $admin = $request->user();
        // if (!$admin || !$admin->hasRole('admin')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized. Admin access required.',
        //     ], 403);
        // }

        // Validation
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'estate_id' => 'required|integer|exists:estates,id',
            'plots' => 'required|array|min:1',
            'plots.*' => 'integer|exists:plots,id',
            'total_price' => 'nullable|numeric|min:0',
            'installment_months' => 'nullable|integer|min:0|max:12',
            'payment_status' => 'required|in:paid,pending,outstanding',
            'acquisition_status' => 'nullable|in:held,transferred',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Fetch customer
            $customer = \App\Models\User::findOrFail($request->user_id);

            // Fetch estate with plot details
            $estate = Estate::with('plotDetail')->findOrFail($request->estate_id);

            if (!$estate->plotDetail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estate plot details not found',
                ], 404);
            }

            // Lock and fetch plots
            $plots = Plot::whereIn('id', $request->plots)
                ->where('estate_id', $estate->id)
                ->where('status', 'available')
                ->lockForUpdate()
                ->get();

            if ($plots->count() !== count($request->plots)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Some selected plots are not available for allocation',
                    'available_plots' => $plots->count(),
                    'requested_plots' => count($request->plots),
                ], 400);
            }

            // Calculate pricing
            $effectivePrice = $estate->plotDetail->promotion_price ?? $estate->plotDetail->price_per_plot;
            $plotsCount = $plots->count();
            $totalPrice = $request->total_price ?? ($effectivePrice * $plotsCount);
            
            // Handle installment details
            $installmentMonths = $request->installment_months ?? null;
            $monthlyPayment = null;
            $paymentSchedule = null;

            if ($installmentMonths && $installmentMonths > 0) {
                $monthlyPayment = round($totalPrice / $installmentMonths, 2);
                
                // Generate payment schedule
                $paymentSchedule = [];
                $startDate = now();
                for ($i = 1; $i <= $installmentMonths; $i++) {
                    $paymentSchedule[] = [
                        'month' => $i,
                        'due_date' => $startDate->copy()->addMonths($i - 1)->format('Y-m-d'),
                        'amount' => $monthlyPayment,
                        'status' => 'pending',
                    ];
                }
            }

            // Generate allocation reference
            $allocationReference = 'ADMIN-ALLOC-' . Str::upper(Str::random(10));

            

            // Create PlotPurchase record using ONLY existing fields
            $purchase = PlotPurchase::create([
                'estate_id' => $estate->id,
                'user_id' => $customer->id,
                'plots' => $plots->pluck('id')->toArray(),
                'total_price' => $totalPrice,
                'installment_months' => $installmentMonths,
                'monthly_payment' => $monthlyPayment,
                'payment_schedule' => $paymentSchedule,
                'payment_reference' => $allocationReference,
                'payment_link' => null, // No payment link for admin allocation
                'payment_status' => $request->payment_status,
                //'acquisition_status' => $request->acquisition_status ?? 'held',
            ]);

            // Mark plots as sold
            foreach ($plots as $plot) {
                $plot->update(['status' => 'sold']);
            }
            // Create CustomerProperty record
            $customerProperty = CustomerProperty::create([
                'user_id' => $customer->id,
                'estate_id' => $estate->id,
                'plots' => $plots->pluck('id')->toArray(),
                'total_price' => $totalPrice,
                'installment_months' => $installmentMonths,
                'payment_status' => $request->payment_status === 'paid' && !$installmentMonths 
                    ? 'fully_paid' 
                    : ($request->payment_status === 'paid' ? 'outstanding' : $request->payment_status),
                'acquisition_status' => $request->acquisition_status ?? 'held',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Property allocated successfully to customer',
                'data' => [
                    'allocation_id' => $purchase->id,
                    'customer_property_id' => $customerProperty->id,
                    'allocation_reference' => $allocationReference,
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name ?? ($customer->first_name . ' ' . $customer->last_name),
                        'email' => $customer->email,
                    ],
                    'estate' => [
                        'id' => $estate->id,
                        'title' => $estate->title,
                        'town_or_city' => $estate->town_or_city,
                        'state' => $estate->state,
                    ],
                    'plots' => $plots->map(fn($plot) => [
                        'id' => $plot->id,
                        'plot_id' => $plot->plot_id,
                        'coordinate' => $plot->coordinate,
                        'status' => $plot->status,
                    ]),
                    'pricing' => [
                        'total_price' => $totalPrice,
                        'installment_months' => $installmentMonths,
                        'monthly_payment' => $monthlyPayment,
                        'payment_schedule' => $paymentSchedule,
                    ],
                    'payment_status' => $request->payment_status,
                    'acquisition_status' => $request->acquisition_status ?? 'held',
                    'allocated_at' => now()->toISOString(),
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to allocate property',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/allocations",
     *     tags={"Admin - Property Management"},
     *     summary="Get all property allocations",
     *     description="Retrieve a list of all properties with filtering options",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by customer user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="estate_id",
     *         in="query",
     *         description="Filter by estate ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="payment_status",
     *         in="query",
     *         description="Filter by payment status",
     *         @OA\Schema(type="string", enum={"paid", "pending", "outstanding"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of allocations retrieved successfully"
     *     )
     * )
     */
    public function getAllocations(Request $request)
    {
        // $admin = $request->user();
        // if (!$admin || !$admin->hasRole('admin')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized. Admin access required.',
        //     ], 403);
        // }

        // Eager load relationships
        $query = PlotPurchase::with([
            'estate:id,title,town_or_city,state,size',
            'estate.plotDetail:id,estate_id,price_per_plot,promotion_price,available_plot',
            'user:id,first_name,last_name,email,phone'
        ]);

        // Apply filters
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('estate_id')) {
            $query->where('estate_id', $request->estate_id);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Date range filter
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 20);
        $allocations = $query->paginate($perPage);

        // Transform the data for better readability
        $allocations->getCollection()->transform(function ($allocation) {
            // Fetch plot details
            $plotDetails = [];
            if (!empty($allocation->plots)) {
                $plotModels = Plot::whereIn('id', $allocation->plots)->get();
                $plotDetails = $plotModels->map(function ($plot) {
                    return [
                        'id' => $plot->id,
                        'plot_id' => $plot->plot_id,
                        'coordinate' => $plot->coordinate,
                        'status' => $plot->status,
                    ];
                });
            }

            return [
                'id' => $allocation->id,
                'allocation_reference' => $allocation->payment_reference,
                'customer' => [
                    'id' => $allocation->user->id ?? null,
                    'name' => trim(($allocation->user->first_name ?? '') . ' ' . ($allocation->user->last_name ?? '')),
                    'email' => $allocation->user->email ?? null,
                    'phone' => $allocation->user->phone ?? null,
                ],
                'estate' => [
                    'id' => $allocation->estate->id ?? null,
                    'title' => $allocation->estate->title ?? null,
                    'location' => trim(($allocation->estate->town_or_city ?? '') . ', ' . ($allocation->estate->state ?? '')),
                    'size' => $allocation->estate->size ?? null,
                ],
                'plots' => $plotDetails,
                'plot_count' => count($allocation->plots ?? []),
                'pricing' => [
                    'total_price' => $allocation->total_price,
                    'installment_months' => $allocation->installment_months,
                    'monthly_payment' => $allocation->monthly_payment,
                ],
                'payment_status' => $allocation->payment_status,
                'acquisition_status' => $allocation->acquisition_status,
                'created_at' => $allocation->created_at->toISOString(),
                'updated_at' => $allocation->updated_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Allocations retrieved successfully',
            'data' => $allocations->items(),
            'pagination' => [
                'current_page' => $allocations->currentPage(),
                'per_page' => $allocations->perPage(),
                'total' => $allocations->total(),
                'last_page' => $allocations->lastPage(),
                'from' => $allocations->firstItem(),
                'to' => $allocations->lastItem(),
            ],
        ], 200);
    }
    

    
}
