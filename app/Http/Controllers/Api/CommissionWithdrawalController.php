<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CommissionWithdrawal;
use App\Models\AgentCommission;

class CommissionWithdrawalController extends Controller
{
    /**
 * @OA\Post(
 *      path="/api/v1/agent/withdraw",
 *      operationId="requestCommissionWithdrawal",
 *      tags={"Agent - Commission"},
 *      summary="Request commission withdrawal",
 *      description="Allows an agent to request a withdrawal from their available commission balance. The withdrawal is created with a pending status.",
 *      security={{"sanctum": {}}},
 *
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"agent_id", "amount"},
 *              @OA\Property(
 *                  property="agent_id",
 *                  type="integer",
 *                  example=5,
 *                  description="Unique ID of the agent making the withdrawal request"
 *              ),
 *              @OA\Property(
 *                  property="amount",
 *                  type="number",
 *                  format="float",
 *                  example=15000,
 *                  description="Amount to withdraw from commission balance"
 *              ),
 *              @OA\Property(
 *                  property="description",
 *                  type="string",
 *                  example="Weekly commission withdrawal",
 *                  description="Optional description for the withdrawal request"
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=201,
 *          description="Withdrawal request created successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="status", type="boolean", example=true),
 *              @OA\Property(
 *                  property="message",
 *                  type="string",
 *                  example="Withdrawal request created successfully."
 *              ),
 *              @OA\Property(
 *                  property="data",
 *                  type="object",
 *                  @OA\Property(property="id", type="integer", example=12),
 *                  @OA\Property(property="agent_id", type="integer", example=5),
 *                  @OA\Property(property="commission_id", type="integer", example=3),
 *                  @OA\Property(property="amount", type="number", format="float", example=15000),
 *                  @OA\Property(property="balance_before", type="number", format="float", example=45000),
 *                  @OA\Property(property="balance_after", type="number", format="float", example=30000),
 *                  @OA\Property(property="description", type="string", example="Weekly commission withdrawal"),
 *                  @OA\Property(property="status", type="string", example="pending"),
 *                  @OA\Property(property="created_at", type="string", format="date-time"),
 *                  @OA\Property(property="updated_at", type="string", format="date-time")
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=400,
 *          description="Withdrawal amount exceeds available balance",
 *          @OA\JsonContent(
 *              @OA\Property(property="status", type="boolean", example=false),
 *              @OA\Property(
 *                  property="message",
 *                  type="string",
 *                  example="Withdrawal amount exceeds available balance."
 *              )
 *          )
 *      ),
 *
 *      @OA\Response(
 *          response=422,
 *          description="Validation error"
 *      ),
 *
 *      @OA\Response(
 *          response=401,
 *          description="Unauthenticated"
 *      )
 * )
 */

    // Agent requests withdrawal
    public function requestWithdrawal(Request $request)
    {
        
        $request->validate([
            'agent_id' => 'required',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $commission = AgentCommission::where('agent_id', $request->agent_id)->firstOrFail();

        if ($request->amount > $commission->amount) {
            return response()->json([
                'status' => false,
                'message' => 'Withdrawal amount exceeds available balance.'
            ], 400);
        }

        $balanceBefore = $commission->amount;
        $balanceAfter = $commission->amount - $request->amount;

        // Create withdrawal record
        $withdrawal = CommissionWithdrawal::create([
            'agent_id' => $agent->id,
            'commission_id' => $commission->id,
            'amount' => $request->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Withdrawal request created successfully.',
            'data' => $withdrawal
        ], 201);
    }

    // Agent views their withdrawals
    public function myWithdrawals(Request $request)
    {
        $agent_id = $request->agent_id;
        $total_withdrawals = CommissionWithdrawal::where('agent_id', $agent_id)
            ->where('status', 'approved')
            ->sum('amount');
        $pending_withdrawals = CommissionWithdrawal::where('agent_id', $agent_id)
            ->where('status', 'pending')
            ->sum('amount');
        $withdrawals = CommissionWithdrawal::where('agent_id', $agent_id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Withdrawals retrieved successfully.',
            'total_withdrawals' => $total_withdrawals,
            'pending_withdrawals' => $pending_withdrawals,
            'data' => $withdrawals
        ], 200);
    }

    // Admin views all withdrawal requests
    public function allWithdrawals()
    {
        $withdrawals = CommissionWithdrawal::with('agent')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'All withdrawals retrieved successfully.',
            'data' => $withdrawals
        ], 200);
    }

    // Admin approves a withdrawal
    public function approve($id)
    {
        $withdrawal = CommissionWithdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['status' => false, 'message' => 'Withdrawal already processed.'], 400);
        }

        $withdrawal->status = 'approved';
        $withdrawal->save();

        // Update agent commission balance
        $commission = $withdrawal->commission;
        $commission->amount = $withdrawal->balance_after;
        $commission->save();

        return response()->json([
            'status' => true,
            'message' => 'Withdrawal approved successfully.'
        ], 200);
    }

    // Admin rejects a withdrawal
    public function reject($id)
    {
        $withdrawal = CommissionWithdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['status' => false, 'message' => 'Withdrawal already processed.'], 400);
        }

        $withdrawal->status = 'rejected';
        $withdrawal->save();

        return response()->json([
            'status' => true,
            'message' => 'Withdrawal rejected successfully.'
        ], 200);
    }
}
