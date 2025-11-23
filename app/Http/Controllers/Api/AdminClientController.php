<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\MailService;
use App\Mail\ResetPasswordMail;
use App\Models\PasswordResetLog;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AdminClientController extends Controller {

     // Admin resets a client's password using client email
    /**
     * @OA\Post(
     *      path="/api/v1/admin/reset-client-password",
     *      operationId="resetClientPassword",
     *      tags={"Admin - User Management"},
     *      summary="Reset a client's password using their email address",
     *      description="Allows an admin to reset a client's password by providing the client's registered email and a new password. The new password is updated and sent to the client's email address.",
     *      security={{"sanctum": {}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"client_email","new_password"},
     *              @OA\Property(property="client_email", type="string", format="email", example="client@example.com", description="The registered email of the client"),
     *              @OA\Property(property="new_password", type="string", format="password", minLength=6, example="newSecurePass123", description="The new password to assign to the client")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Password reset successfully and sent to client email.",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Password reset successfully and sent to client email."),
     *              @OA\Property(property="client", type="string", example="client@example.com"),
     *              @OA\Property(property="reset_by", type="string", example="admin@gofill.com")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Access denied. Only admins can reset passwords.",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Access denied. Only admins can reset passwords.")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The given data was invalid."),
     *              @OA\Property(property="errors", type="object",
     *                  @OA\Property(property="client_email", type="array", @OA\Items(type="string", example="The client_email field is required.")),
     *                  @OA\Property(property="new_password", type="array", @OA\Items(type="string", example="The new_password must be at least 6 characters."))
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to reset password due to an internal error."),
     *              @OA\Property(property="errors", type="object",
     *                  @OA\Property(property="server", type="array", @OA\Items(type="string", example="An unexpected error occurred. Please try again later."))
     *              )
     *          )
     *      )
     * )
     */


    public function resetClientPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'new_password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $admin = $request->user();

        // Confirm admin privilege
        if ($admin->account_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only admins can reset passwords.'
            ], 403);
        }

        // Get the client user
        $client = User::find($request->user_id);

        // check user existence
        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Update password
        $client->password = Hash::make($request->new_password);
        $client->save();

        // Log reset action
        PasswordResetLog::create([
            'user_id' => $client->id,
            'admin_id' => $admin->id,
            'reset_at' => now(),
        ]);

        // Send new password to client email
        try {
              $sent = MailService::send($client->email, new ResetPasswordMail($client, $request->new_password), [
                     'queue' => false,
                    ]);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'client_email' => $client->email,
                'error' => $e->getMessage(),
                'time' => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully and sent to client email.',
            'client' => $client->email,
            'reset_by' => $admin->email,
        ], 200);
    }
}
