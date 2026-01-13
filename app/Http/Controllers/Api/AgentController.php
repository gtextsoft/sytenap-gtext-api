<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\AgentCommission;
use App\Models\CommissionHistory;
use App\Models\Estate;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\Referral;

class AgentController extends Controller {

    /**
 * @OA\Post(
 *      path="/api/v1/agent/balance",
 *      operationId="getAgentBalance",
 *      tags={"Agent - Commission"},
 *      summary="Get current commission balance of an agent",
 *      description="Returns the total accumulated commission balance of a specific agent.",
 *      security={{"sanctum": {}}},
 *
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"agent_id"},
 *              @OA\Property(property="agent_id", type="integer", example=5)
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=200,
 *          description="Commission balance returned successfully.",
 *          @OA\JsonContent(
 *              @OA\Property(property="agent_id", type="integer", example=5),
 *              @OA\Property(property="balance", type="number", format="float", example=45000.50)
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=422,
 *          description="Validation error"
 *      )
 * )
 */

    

                                    public function balance( Request $request ) {
                                            $validator = Validator::make( $request->all(), [
                                                'agent_id' => 'required',
                                                ] );

                                        if ( $validator->fails() ) {
                                            return response()->json( [ 'errors' => $validator->errors() ], 422 );
                                        }

                                        $agentId = $request->agent_id;

                                        $exists = AgentCommission::where( 'agent_id', $agentId )->exists();

                                        if ( !$exists ) {
                                            AgentCommission::create( [
                                                'agent_id' => $agentId,
                                                'amount' => 0
                                            ] );
                                        }

                                        $balance = AgentCommission::where( 'agent_id', $agentId )->sum( 'amount' );

                                        return response()->json( [
                                            'agent_id' => $agentId,
                                            'balance' => $balance
                                        ] );
                                    }

                                   /**
 * @OA\Post(
 *      path="/api/v1/agent/commission-history",
 *      operationId="getAgentCommissionHistory",
 *      tags={"Agent - Commission"},
 *      summary="Get full commission history of an agent",
 *      description="Returns every commission payment linked to the agent, including estate, plot, and description.",
 *      security={{"sanctum": {}}},
 *
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"agent_id"},
 *              @OA\Property(property="agent_id", type="integer", example=5)
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=200,
 *          description="Commission history retrieved successfully.",
 *          @OA\JsonContent(
 *              type="array",
 *              @OA\Items(
 *                  @OA\Property(property="id", type="integer", example=12),
 *                  @OA\Property(property="agent_id", type="integer", example=5),
 *                  @OA\Property(property="commission_id", type="integer", example=3),
 *                  @OA\Property(property="estate_id", type="integer", example=2),
 *                  @OA\Property(property="plot_id", type="integer", example=10),
 *                  @OA\Property(property="description", type="string", example="Commission for plot sale"),
 *                  @OA\Property(
 *                      property="commission",
 *                      type="object",
 *                      @OA\Property(property="amount", type="number", example=15000.00)
 *                  )
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=422,
 *          description="Validation error"
 *      )
 * )
 */


 public function history( Request $request ) {
     $validator = Validator::make( $request->all(), [
                'agent_id' => 'required|exists:agent_commissions,agent_id',
        ] );

        if ( $validator->fails() ) {
                    return response()->json( [ 'errors' => $validator->errors() ], 422 );
        }

        $history = CommissionHistory::where( 'agent_id', $request->agent_id )
                        ->with('commission', 'estate', 'plot')
                        ->orderBy( 'id', 'desc' )
                    ->paginate( 5 );

                return response()->json( $history );
    }

        /**
     * @OA\Post(
     *      path="/api/v1/agent/dashboard/stats",
     *      operationId="getAgentDashboardStats",
     *      tags={"Agent - Dashboard"},
     *      summary="Get agent dashboard statistics",
     *      description="Returns dashboard statistics for an agent including total commission earned and number of available properties.",
     *      security={{"sanctum": {}}},
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"agent_id"},
     *              @OA\Property(
     *                  property="agent_id",
     *                  type="integer",
     *                  example=5,
     *                  description="Unique ID of the agent"
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Dashboard statistics retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="agent_id", type="integer", example=5),
     *              @OA\Property(
     *                  property="total_commission",
     *                  type="number",
     *                  format="float",
     *                  example=125000.50,
     *                  description="Total commission earned by the agent"
     *              ),
     *              @OA\Property(
     *                  property="available_properties",
     *                  type="integer",
     *                  example=42,
     *                  description="Total number of available (non-draft) properties"
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
     *                  example={"agent_id": {"The selected agent id is invalid."}}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated"
     *      )
     * )
     */

    public function dashboardStats(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|exists:agent_commissions,agent_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $agentId = $request->agent_id;

        $totalCommission = AgentCommission::where('agent_id', $agentId)->sum('amount');

        $availableProperties = Estate::where('status','!=','draft')->count();

        return response()->json([
            'agent_id' => $agentId,
            'total_commission' => $totalCommission,
            'available_properties' => $availableProperties,
        ]);
    }


     

public function getReferralInfo(Request $request): JsonResponse
{
    // Validate the input
    $validator = Validator::make($request->all(), [
        'agent_id' => 'required', 
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422);
    }

    $agentId = $request->agent_id;

    // Get referral info
    $referral = Referral::where('user_id', $agentId)->first();

    if (!$referral) {
        return response()->json([
            'success' => false,
            'message' => 'Referral info not found for this agent',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Referral info retrieved successfully',
        'data' => [
            'agent_id' => $agentId,
            'referral_code' => $referral->referral_code,
        ]
    ], 200);
}
                                                                         
}

    
