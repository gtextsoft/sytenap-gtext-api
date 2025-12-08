<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\AgentCommission;
use App\Models\CommissionHistory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class AgentController extends Controller {
    /**
    * @OA\Post(
        *      path = '/api/v1/agent/balance',
        *      operationId = 'getAgentBalance',
        *      tags = {
            'Agent - Commission'}
            ,
            *      summary = 'Get current commission balance of an agent',
            *      description = 'Returns the total accumulated commission balance of a specific agent.',
            *      security = {
                {
                    'sanctum': {
                    }
                }
            }
            ,
            *
            *      @OA\RequestBody(
                *          required = true,
                *          @OA\JsonContent(
                    *              required = {
                        'agent_id'}
                        ,
                        *              @OA\Property( property = 'agent_id', type = 'integer', example = 5 )
                        * )
                        * ),
                        *
                        *      @OA\Response(
                            *          response = 200,
                            *          description = 'Commission balance returned successfully.',
                            *          @OA\JsonContent(
                                *              @OA\Property( property = 'agent_id', type = 'integer', example = 5 ),
                                *              @OA\Property( property = 'balance', type = 'number', format = 'float', example = 45000.50 )
                                * )
                                * ),
                                *
                                *      @OA\Response(
                                    *          response = 422,
                                    *          description = 'Validation error'
                                    * )
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
                                        *      path = '/api/v1/agent/commission-history',
                                        *      operationId = 'getAgentCommissionHistory',
                                        *      tags = {
                                            'Agent - Commission'}
                                            ,
                                            *      summary = 'Get full commission history of an agent',
                                            *      description = 'Returns every commission payment linked to the agent, including estate, plot and description.',
                                            *      security = {
                                                {
                                                    'sanctum': {
                                                    }
                                                }
                                            }
                                            ,
                                            *
                                            *      @OA\RequestBody(
                                                *          required = true,
                                                *          @OA\JsonContent(
                                                    *              required = {
                                                        'agent_id'}
                                                        ,
                                                        *              @OA\Property( property = 'agent_id', type = 'integer', example = 5 )
                                                        * )
                                                        * ),
                                                        *
                                                        *      @OA\Response(
                                                            *          response = 200,
                                                            *          description = 'Commission history retrieved successfully.',
                                                            *          @OA\JsonContent(
                                                                *              type = 'array',
                                                                *              @OA\Items(
                                                                    *                  @OA\Property( property = 'id', type = 'integer', example = 12 ),
                                                                    *                  @OA\Property( property = 'agent_id', type = 'integer', example = 5 ),
                                                                    *                  @OA\Property( property = 'commission_id', type = 'integer', example = 3 ),
                                                                    *                  @OA\Property( property = 'estate_id', type = 'integer', example = 2 ),
                                                                    *                  @OA\Property( property = 'plot_id', type = 'integer', example = 10 ),
                                                                    *                  @OA\Property( property = 'description', type = 'string', example = 'Commission for plot sale' ),
                                                                    *                  @OA\Property(
                                                                        *                      property = 'commission',
                                                                        *                      type = 'object',
                                                                        *                      @OA\Property( property = 'amount', type = 'number', example = 15000.00 )
                                                                        * ),
                                                                        * )
                                                                        * )
                                                                        * ),
                                                                        *
                                                                        *      @OA\Response(
                                                                            *          response = 422,
                                                                            *          description = 'Validation error'
                                                                            * )
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
                                                                                ->with( 'commission' )
                                                                                ->orderBy( 'id', 'desc' )
                                                                                ->paginate( 5 );

                                                                                return response()->json( $history );
                                                                            }
                                                                        }
