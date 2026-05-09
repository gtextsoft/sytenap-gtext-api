<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model {
    protected $fillable = [
        'value',
        'type', // e.g 'percentage' or 'flat'
        'status',
        'min',
        'max',
        'agent_role', // e.g 'associate', 'staff',
    ];
}
