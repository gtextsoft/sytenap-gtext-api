<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class AuthController extends Controller
{
   protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Register a new user and send OTP for email verification
     */

    /**
     * @OA\Post(
     *      path="/api/auth/register",
     *      operationId="register",
     *      tags={"Authentication"},
     *      summary="Register a new user",
     *      description="Register a new user and send OTP for email verification",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"first_name","last_name","email","password","password_confirmation","state","country"},
     *              @OA\Property(property="first_name", type="string", example="John"),
     *              @OA\Property(property="last_name", type="string", example="Doe"),
     *              @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="password123"),
     *              @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *              @OA\Property(property="state", type="string", example="Lagos"),
     *              @OA\Property(property="country", type="string", example="Nigeria")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="User registered successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User registered successfully. Please check your email for verification code."),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="user", type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="first_name", type="string", example="John"),
     *                      @OA\Property(property="last_name", type="string", example="Doe"),
     *                      @OA\Property(property="email", type="string", example="john@example.com"),
     *                      @OA\Property(property="email_verified", type="boolean", example=false)
     *                  ),
     *                  @OA\Property(property="otp_expires_in_minutes", type="integer", example=10)
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Create user with email_verified_at as null
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'state' => $request->state,
                'country' => $request->country,
                'email_verified_at' => null, // User is not verified yet
            ]);

            // Generate and send OTP
            $otpResult = $this->otpService->generateAndSendOtp(
                $request->email, 
                'email_verification'
            );

            if (!$otpResult['success']) {
                return response()->json([
                    'message' => 'User created but failed to send verification email',
                    'error' => $otpResult['message']
                ], 500);
            }

            return response()->json([
                'message' => 'User registered successfully. Please check your email for verification code.',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email_verified' => false
                ],
                'otp_expires_in_minutes' => $otpResult['expires_in_minutes']
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/verify-email",
     *     summary="Verify user email address via OTP",
     *     description="Verifies a user's email address using a One-Time Password (OTP) sent to their email. 
     *     If verification is successful, the user's email_verified_at timestamp is updated.",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "otp"},
     *             @OA\Property(property="email", type="string", format="email", example="johndoe@example.com", description="The registered email address of the user."),
     *             @OA\Property(property="otp", type="string", minLength=6, maxLength=6, example="123456", description="The 6-digit OTP sent to the user's email.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email verified successfully"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="email", type="string", example="johndoe@example.com"),
     *                 @OA\Property(property="first_name", type="string", example="John"),
     *                 @OA\Property(property="last_name", type="string", example="Doe"),
     *                 @OA\Property(property="email_verified", type="boolean", example=true),
     *                 @OA\Property(property="verified_at", type="string", format="date-time", example="2025-08-15T10:23:45Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="OTP verification failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid OTP")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="status", type="integer", example=0),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={"email": {"The email field is required."}, "otp": {"The otp field must be 6 characters."}}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email verification failed"),
     *             @OA\Property(property="error", type="string", example="Unexpected error occurred")
     *         )
     *     )
     * )
     */
      
    public function verifyEmail(Request $request): JsonResponse
    {
         $validator = \Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            
            return response()->json([
                    'message' => 'Validation failed',
                    'status' => 0,
                    'errors' => $validator->errors()
                ], 422);
        }

        try {
            // Verify OTP
            $verificationResult = $this->otpService->verifyOtp(
                $request->email,
                $request->otp,
                'email_verification'
            );

            if (!$verificationResult['success']) {
                return response()->json([
                    'message' => $verificationResult['message']
                ], 400);
            }

            // Update user's email_verified_at timestamp
            $user = User::where('email', $request->email)->first();
            $user->update(['email_verified_at' => now()]);

            return response()->json([
                'message' => 'Email verified successfully',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email_verified' => true,
                    'verified_at' => $user->email_verified_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Email verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/resend-otp",
     *     summary="Resend email verification OTP",
     *     description="Resends a One-Time Password (OTP) to the provided email address for email verification. 
     *     If the email is already verified, the request will fail.",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="johndoe@example.com", description="The registered email address of the user.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Verification code sent successfully"),
     *             @OA\Property(property="otp_expires_in_minutes", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Email already verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email is already verified")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The email field is required."),
     *             @OA\Property(property="errors", type="object", example={"email": {"The selected email is invalid or does not exist."}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to send OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to send verification email"),
     *             @OA\Property(property="error", type="string", example="Mail server not responding")
     *         )
     *     )
     * )
    */

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email'
        ]);

        try {
            // Check if user is already verified
            $user = User::where('email', $request->email)->first();
            if ($user->email_verified_at) {
                return response()->json([
                    'message' => 'Email is already verified'
                ], 400);
            }

            // Generate and send new OTP
            $otpResult = $this->otpService->generateAndSendOtp(
                $request->email,
                'email_verification'
            );

            if (!$otpResult['success']) {
                return response()->json([
                    'message' => 'Failed to send verification email',
                    'error' => $otpResult['message']
                ], 500);
            }

            return response()->json([
                'message' => 'Verification code sent successfully',
                'otp_expires_in_minutes' => $otpResult['expires_in_minutes']
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to resend OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *      path="/api/auth/login",
     *      operationId="login",
     *      tags={"Authentication"},
     *      summary="Login user",
     *      description="Login user with email and password",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email","password"},
     *              @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="password123")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Login successful",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Login successful."),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="user", type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="email", type="string", example="john@example.com"),
     *                      @OA\Property(property="first_name", type="string", example="John"),
     *                      @OA\Property(property="last_name", type="string", example="Doe")
     *                  ),
     *                  @OA\Property(property="token", type="string", example="1|abc123..."),
     *                  @OA\Property(property="token_type", type="string", example="Bearer")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Invalid credentials"
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Email not verified"
     *      )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        try {
            
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Find user by email
            $user = User::where('email', $request->email)->first();

            // Check if user exists (this should be handled by validation, but double-check)
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                    'errors' => [
                        'email' => ['No account found with this email address.']
                    ],
                    'data' => null
                ], 401);
            }

            // Check if email is verified
            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not verified. Please verify your email before logging in.',
                    'errors' => [
                        'email' => ['Email address is not verified. Check your inbox for verification code.']
                    ],
                    'data' => [
                        'requires_verification' => true,
                        'email' => $user->email
                    ]
                ], 403);
            }

            // Verify password
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                    'errors' => [
                        'password' => ['The password you entered is incorrect.']
                    ],
                    'data' => null
                ], 401);
            }

            // Generate API token (using Laravel Sanctum)
            $token = $user->createToken('api-token')->plainTextToken;

            // Update last login timestamp
            //$user->update(['last_login_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'state' => $user->state,
                        'country' => $user->country,
                        'email_verified' => true,
                        'email_verified_at' => $user->email_verified_at,
                        //'last_login_at' => $user->last_login_at,
                        'created_at' => $user->created_at
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ],
                'errors' => null
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'data' => null
            ], 422);

        } catch (\Exception $e) {
            \Log::error('User login failed: ' . $e->getMessage(), [
                'email' => $request->input('email'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Login failed due to an unexpected error.',
                'errors' => [
                    'server' => ['An unexpected error occurred. Please try again later.']
                ],
                'data' => $e->getMessage()
            ], 500);
        }
    }

}
