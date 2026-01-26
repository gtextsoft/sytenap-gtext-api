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
use App\Models\Estate;
use App\Notifications\AdminEstateAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;


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
                $agentId = $data['data']['user']['id'] ?? null;
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

  
    /**
 * @OA\Post(
 *      path="/api/v1/admin/create-new-admin",
 *      operationId="createAdminOrLegalUser",
 *      tags={"Admin - Estate Management"},
 *      summary="Create a new admin or legal user",
 *      description="Allows an authenticated admin to create another admin or legal user. Admin users are assigned to an estate, while legal users are not. Email is auto-verified and login credentials are sent via email.",
 *      security={{"sanctum": {}}},
 *
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"first_name","last_name","email","account_type"},
 *
 *              @OA\Property(
 *                  property="first_name",
 *                  type="string",
 *                  example="John",
 *                  description="First name of the user"
 *              ),
 *
 *              @OA\Property(
 *                  property="last_name",
 *                  type="string",
 *                  example="Doe",
 *                  description="Last name of the user"
 *              ),
 *
 *              @OA\Property(
 *                  property="email",
 *                  type="string",
 *                  format="email",
 *                  example="user@example.com",
 *                  description="Email address of the user"
 *              ),
 *
 *              @OA\Property(
 *                  property="account_type",
 *                  type="string",
 *                  enum={"admin","legal"},
 *                  example="admin",
 *                  description="Type of account to create"
 *              ),
 *
 *              @OA\Property(
 *                  property="estate_id",
 *                  type="integer",
 *                  example=5,
 *                  nullable=true,
 *                  description="Required only when account_type is admin"
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=201,
 *          description="User created successfully",
 *          @OA\JsonContent(
 *              @OA\Property(
 *                  property="message",
 *                  type="string",
 *                  example="Admin account created successfully"
 *              ),
 *              @OA\Property(
 *                  property="user",
 *                  type="object",
 *                  @OA\Property(property="id", type="integer", example=22),
 *                  @OA\Property(property="first_name", type="string", example="John"),
 *                  @OA\Property(property="last_name", type="string", example="Doe"),
 *                  @OA\Property(property="email", type="string", example="user@example.com"),
 *                  @OA\Property(property="account_type", type="string", example="admin"),
 *                  @OA\Property(property="email_verified_at", type="string", format="date-time")
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=422,
 *          description="Validation error",
 *          @OA\JsonContent(
 *              @OA\Property(
 *                  property="errors",
 *                  type="object",
 *                  example={
 *                      "estate_id": {"The estate id field is required when account type is admin."}
 *                  }
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=401,
 *          description="Unauthenticated"
 *      ),
 *
 *      @OA\Response(
 *          response=403,
 *          description="Unauthorized"
 *      )
 * )
 */

    public function createAdminAndAssignEstate(Request $request)
    {
        $request->validate([
            'first_name'   => 'required|string',
            'last_name'    => 'required|string',
            'email'        => 'required|email|unique:users,email',
            'account_type' => 'required|in:admin,legal,accountant',
            'estate_id'    => 'required_if:account_type,admin|exists:estates,id',
        ]);

        $user = $request->user();

        if ($user->account_type !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        return DB::transaction(function () use ($request) {

            // 1. Generate password
            $plainPassword = Str::random(10);

            // 2. Create User (Admin or Legal)
            $newUser = User::create([
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'email'             => $request->email,
                'account_type'      => $request->account_type,
                'state'             => 'Lagos',
                'country'           => 'Nigeria',
                'password'          => Hash::make($plainPassword),
                'email_verified_at' => now(), // auto-verified
            ]);

            $estate = null;

            // 3. Assign Estate ONLY if account_type is admin
            if ($request->account_type === 'admin') {

                $estate = Estate::findOrFail($request->estate_id);

                $admins = $estate->estate_admin ?? [];

                if (!in_array($newUser->email, $admins)) {
                    $admins[] = $newUser->email;
                }

                $estate->update([
                    'estate_admin' => $admins,
                ]);
            }

            // 4. Send Notification (Estate info optional)
            Notification::send(
                $newUser,
                new AdminEstateAssignedNotification(
                    estate: $estate,
                    password: $plainPassword
                )
            );

            return response()->json([
                'message' => ucfirst($request->account_type) . ' account created successfully',
                'user'    => $newUser,
            ], 201);
        });
    }
}









