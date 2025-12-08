<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AgentCommission extends Model {
    protected $fillable = [
        'agent_id',
        'amount',
    ];

    public function agent() {
        return $this->belongsTo( User::class, 'agent_id' );
    }

    public function histories() {
        return $this->hasMany( CommissionHistory::class, 'commission_id' );
    }

}
