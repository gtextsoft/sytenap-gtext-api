<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionWithdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'commission_id',
        'amount',
        'balance_before',
        'balance_after',
        'status',
        'description',
        'withdrawal_date',
    ];

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function commission()
    {
        return $this->belongsTo(AgentCommission::class, 'commission_id');
    }
}
