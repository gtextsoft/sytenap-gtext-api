<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Referral;
use Illuminate\Http\JsonResponse;

class AdminReferralController extends Controller
{
    /**
 * @OA\Get(
 *     path="/api/v1/admin/referrals",
 *     operationId="getAllReferrals",
 *     summary="Get all referral records",
 *     description="Returns a list of all referral records in the system. Each record contains the user ID and referral code. Accessible by admin only.",
 *     tags={"Admin - Referrals"},
 *     security={{"sanctum": {}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Referral records retrieved successfully",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="user_id", type="integer", example=15),
 *                     @OA\Property(property="referral_code", type="string", example="REF-8FJ39K")
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Unauthorized"
 *     )
 * )
 */

    public function index(): JsonResponse
    {
        $agents = Referral::get();
        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }
}

