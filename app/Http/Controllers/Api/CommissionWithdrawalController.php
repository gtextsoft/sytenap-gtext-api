<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CommissionWithdrawal;
use App\Models\AgentCommission;

class CommissionWithdrawalController extends Controller
{
    // Agent requests withdrawal
    public function requestWithdrawal(Request $request)
    {
        $agent = $request->user();
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $commission = AgentCommission::where('agent_id', $agent->id)->firstOrFail();

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
        $agent = $request->user();
        $withdrawals = CommissionWithdrawal::where('agent_id', $agent->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Withdrawals retrieved successfully.',
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
