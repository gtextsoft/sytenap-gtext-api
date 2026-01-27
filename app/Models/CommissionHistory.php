<?php

namespace App\Models;

use App\Models\User;
use App\Models\AgentCommission;
use Illuminate\Database\Eloquent\Model;

class CommissionHistory extends Model {
    protected $fillable = [
        'agent_id',
        'commission_id',
        'plot_id',
        'estate_id',
        'description',
    ];

    public function commission() {
        return $this->belongsTo( AgentCommission::class, 'commission_id' );
    }

    public function agent() {
        return $this->belongsTo( User::class, 'agent_id' );
    }

    public function plot() {
        return $this->belongsTo( Plot::class, 'plot_id' );
    }

    public function estate() {
        return $this->belongsTo( Estate::class, 'estate_id' );
    }
}
