<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminReferralController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/admin/referrals",
     *     summary="Get list of all agents and their referrals",
     *     description="This endpoint returns all agents with their referred users. Only accessible by admin.",
     *     tags={"Admin - Referrals"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of agents and their referrals",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="agent_id", type="integer", example=5),
     *                     @OA\Property(property="agent_name", type="string", example="John Doe"),
     *                     @OA\Property(property="agent_email", type="string", example="john@example.com"),
     *                     @OA\Property(property="referrals_count", type="integer", example=3),
     *                     @OA\Property(property="referred_users", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=12),
     *                             @OA\Property(property="name", type="string", example="Mary Smith"),
     *                             @OA\Property(property="email", type="string", example="mary@example.com")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(): JsonResponse
    {
        $agents = User::with(['referrals.referredUser'])
            ->whereHas('referrals') // Only users who have referrals
            ->get()
            ->map(function ($agent) {
                return [
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->first_name . ' ' . $agent->last_name,
                    'agent_email' => $agent->email,
                    'referrals_count' => $agent->referrals->count(),
                    'referred_users' => $agent->referrals->map(function ($referral) {
                        return [
                            'id' => optional($referral->referredUser)->id,
                            'name' => optional($referral->referredUser)->first_name . ' ' . optional($referral->referredUser)->last_name,
                            'email' => optional($referral->referredUser)->email,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }
}

