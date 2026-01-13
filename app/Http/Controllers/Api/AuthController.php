<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\OtpService;
use App\Models\Referral;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

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
    public function register(RegisterRequest $request)
    {
        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'state' => $request->state,
                'country' => $request->country,
                'email_verified_at' => null,
            ]);

            $otpResult = $this->otpService->generateAndSendOtp($request->email, 'email_verification');

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
     * Verify user email using OTP
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
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

            $user = User::where('email', $request->email)->first();
            $user->email_verified_at = Carbon::now();
            $user->save();

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
     * Resend OTP for email verification
     */
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email is already verified'], 400);
        }

        try {
            $otpResult = $this->otpService->generateAndSendOtp($request->email, 'email_verification');

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
     * User login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user->email_verified_at) {
                $this->otpService->generateAndSendOtp($request->email, 'email_verification');

                return response()->json([
                    'success' => false,
                    'message' => 'Email not verified. Check your inbox.',
                    'errors' => ['email' => ['Email address is not verified.']],
                    'data' => ['requires_verification' => true, 'email' => $user->email]
                ], 403);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                    'errors' => ['password' => ['Incorrect password.']],
                    'data' => null
                ], 401);
            }

            $token = $user->createToken('api-token')->plainTextToken;
            $this->createReferralIfNotExists($user->id);

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
                        'account_type' => $user->account_type,
                        'created_at' => $user->created_at
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ],
                'errors' => null
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed.',
                'errors' => ['server' => ['Unexpected error occurred.']],
                'data' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agent login via external API
     */
    public function agent_login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $response = Http::post(env('GANDAWEBSITE_URL') . '/sytemap/login.php', [
                'email' => $request->email,
                'password' => $request->password,
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['success']) && $data['success'] === true) {
                $agentId = $data['data']['id'] ?? null;
                $this->createReferralIfNotExists($agentId);

                return response()->json([
                    'status' => 'success',
                    'message' => $data['message'] ?? 'Login successful',
                    'user' => $data['data']
                ], 200);
            }

            return response()->json([
                'status' => 'error',
                'message' => $data['message'] ?? 'Authentication failed'
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to connect to authentication server',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users
     */
    public function index()
    {
        $users = User::select('id', 'first_name', 'last_name', 'email', 'account_type', 'state', 'country', 'created_at')
                     ->orderBy('created_at', 'desc')
                     ->get();

        return response()->json([
            'success' => true,
            'message' => 'Users fetched successfully',
            'data' => $users,
        ]);
    }

    /**
     * Private: Create referral if not exists
     */
    private function createReferralIfNotExists($agentId)
    {
        if (!Referral::where('user_id', $agentId)->exists()) {
            Referral::create([
                'user_id' => $agentId,
                'referral_code' => 'REF-' . strtoupper(Str::random(8)),
            ]);
        }
    }
}
