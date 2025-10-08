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
                $plot->update(['status' => 'sold']);
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

            // If payment succeeded, assign property
            if ($status === 'paid') {
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



}
