<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\PasswordResetLog;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AdminClientController extends Controller {

     // Admin resets a client's password using client email
    public function resetClientPassword(Request $request)
    {
        $request->validate([
            'client_email' => 'required|email|exists:users,email',
            'new_password' => 'required|string|min:6',
        ]);

        $admin = Auth::user();

        // Confirm admin privilege
        if ($admin->account_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only admins can reset passwords.'
            ], 403);
        }

        $client = User::where('email', $request->client_email)->first();

        // Update password
        $client->password = Hash::make($request->new_password);
        $client->save();

        // Log reset action
        PasswordResetLog::create([
            'client_id' => $client->id,
            'admin_id' => $admin->id,
            'reset_at' => now(),
        ]);

        // Send new password to client email
        try {
            $messageBody = "Dear {$client->name},\n\n"
                . "Your password has been reset by the support team.\n\n"
                . "New Password: {$request->new_password}\n\n"
                . "You can now log in using your email and this password.\n\n"
                . "If you did not request this change, please contact support immediately.\n\n"
                . "Best Regards,\nSupport Team";

            Mail::raw($messageBody, function ($message) use ($client) {
                $message->to($client->email)
                        ->subject('Your New Password from Support Team');
            });
        } catch (\Exception $e) {
              // Log the failure for debugging or audit purposes
            \Log::error('Failed to send password reset email', [
                'client_email' => $client->email,
                'error_message' => $e->getMessage(),
                'time' => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully and sent to client email.',
            'client' => $client->email,
            'reset_by' => $admin->email,
        ]);
    }
}
